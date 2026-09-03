# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 7.6.44  | :white_check_mark: |
| 7.6.43  | :white_check_mark: |
| < 7.6.43| :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability within wpDiscuz, please send an email to info@gvectors.com. All security vulnerabilities will be promptly addressed.

## Security Fixes

### Google Client Secret Exposure (Fixed in 7.6.68)

**Severity:** Medium
**Type:** Sensitive Data Exposure
**Affected Setting:** Comments > Settings > Social Login and Share > Google Client Secret
**Affected Versions:** Up to and including 7.6.67. The exposure was present continuously from June 2019, when the Google Login options were added, until 7.6.68.

**Description:**
The Google Client Secret and Client ID were added to the `$jsArgs` array in `WpdiscuzOptions`, which is localized to the front end as `wpdiscuzAjaxObj`. On any site that had those settings filled in, both values were readable in the HTML source of every front-end page, by any visitor.

Neither value was used by the front-end JavaScript. The Google OAuth flow runs entirely server-side in `forms/wpdFormAttr/Login/SocialLogin.php`, which reads the options directly. The Client ID is public by design and carries no risk on its own; the Client Secret is not meant to leave the server.

**Fix Applied:**
1. Removed `googleClientSecret` from the `$jsArgs` array
2. Removed the unused `googleClientID` from the same array
3. Stored option values are untouched, and Google Login continues to work with no reconfiguration

**Recommended Action:**
Sites that had Google Login configured on an affected version should generate a new Client Secret in the Google Cloud Console and save it under Comments > Settings > Social Login and Share. The redirect URIs registered for the Google app limit what a third party can do with the old secret, but it should no longer be treated as private.

**Files Modified:**
- `options/class.WpdiscuzOptions.php` - Removed `googleClientSecret` and `googleClientID` from `$jsArgs`

### CVE-2025-68997 - IDOR Vulnerability (Fixed in 7.6.44)

**Severity:** Medium
**Type:** Insecure Direct Object Reference (IDOR)
**Affected Actions:** `wpdVoteOnComment`, `wpdUserRate`, `wpdFollowUser`, `wpdAddSubscription`

**Description:**
AJAX actions exposed via `admin-ajax.php` were vulnerable to:
1. Authorization bypass - voting on comments from private/restricted posts
2. Mass abuse through direct HTTP requests bypassing frontend protections

**Fix Applied:**
1. **Authorization Check** (IDOR fix):
   - Added post access validation to `voteOnComment()`
   - Verifies post exists and is published
   - Checks user has permission for private posts
   - Blocks access to password-protected post comments for guests
   - Uses `$comment->comment_post_ID` (actual post from DB) for authorization, not user-supplied `postId` parameter - prevents bypass via parameter manipulation

2. **Rate Limiting** (Abuse prevention):
   - Server-side rate limiting on all sensitive AJAX actions
   - Rate limits: vote (20/min), rate (10/min), follow (15/min), subscribe (10/min)
   - Enhanced client fingerprinting (IP + User-Agent + Accept-Language)
   - Rate limiting executes BEFORE nonce validation for maximum protection

**Files Modified:**
- `utils/class.WpdiscuzHelper.php` - Added `checkRateLimit()` and `getClientFingerprint()`
- `utils/class.WpdiscuzHelperAjax.php` - Authorization check + rate limiting on `voteOnComment()`, `userRate()`, `followUser()`
- `utils/class.WpdiscuzHelperEmail.php` - Rate limiting on `addSubscription()`
- `options/class.WpdiscuzOptions.php` - Added `wc_rate_limit_exceeded` phrase

**Verification:**
Security fix can be verified by checking for `@security-fix CVE-2025-68997` annotations in the source code.

## Security Best Practices

1. Always keep wpDiscuz updated to the latest version
2. Use HTTPS on your website
3. Keep WordPress core and other plugins updated
4. Use strong passwords for admin accounts
5. Consider using a Web Application Firewall (WAF)
