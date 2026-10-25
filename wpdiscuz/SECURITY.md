# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 7.6.73  | :white_check_mark: |
| < 7.6.73| :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability within wpDiscuz, please send an email to info@gvectors.com. All security vulnerabilities will be promptly addressed.

## Security Fixes

### Facebook Login Authentication Bypass (Fixed in 7.6.73)

**Severity:** High
**Type:** Authentication Bypass / Account Takeover
**Affected Setting:** Comments > Settings > Social Login and Share > Facebook (legacy flow, "Use Facebook OAuth2" disabled — the default)
**Affected Versions:** Up to and including 7.6.72.

**Description:**
The legacy client-side Facebook login flow passed a Facebook user ID received with the request straight into a Facebook Graph lookup, without confirming that the ID belonged to the owner of the access token the request arrived with. When Facebook returned no email for that profile, the account's email address was synthesized from the profile ID, and an existing account was then matched by that email. Because a Facebook account's ID is public and was used to derive both the user name and the fallback email, a request could be signed in as another visitor's account. Where the matched account was an administrator, this led to full site compromise.

The server-side OAuth2 flow was never affected: it reads the account from the token's own owner via the Graph `/me` node.

**Fix Applied:**
1. Removed the legacy client-side Facebook flow entirely; Facebook login now always uses the server-side OAuth2 flow (`facebookLoginPHP()` / `facebookLoginPHPCallBack()`), which reads the profile of the access token's owner.
2. Removed the `facebookLogin()` method that trusted the client-supplied user ID.
3. Social accounts are now matched by their verified provider user ID (stored in user meta) first, falling back to email only for a real provider email.
4. Removed the "Use Facebook OAuth2" setting; the server-side flow is the only path. Stored option values are untouched and no reconfiguration of wpDiscuz settings is required.

**Recommended Action:**
Sites that used the legacy flow must add their site's OAuth redirect URI, `wp-admin/admin-ajax.php?action=wpd_login_callback&provider=facebook`, to the "Valid OAuth Redirect URIs" of their Facebook app for the Facebook button to keep working. Administrators should review user accounts that originated from Facebook login for any that were not created by their legitimate owner.

**Files Modified:**
- `forms/wpdFormAttr/Login/SocialLogin.php` - Removed `facebookLogin()`; Facebook login always uses the OAuth2 flow
- `forms/wpdFormAttr/Login/Utils.php` - Match social accounts by verified provider user ID, always store the social user ID
- `options/class.WpdiscuzOptions.php` and `options/options-layouts/html-social.php` - Removed the `fbUseOAuth2` option and its setting
- `assets/js/wpdiscuz-social.js`, `assets/js/wpdiscuz-social.min.js`, `assets/js/wpdiscuz-combo.min.js`, `assets/js/wpdiscuz-combo-no_quill.min.js` - Removed the client-side Facebook login flow (Facebook share is unchanged)

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
