# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 7.6.74  | :white_check_mark: |
| < 7.6.74| :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability within wpDiscuz, please send an email to info@gvectors.com. All security vulnerabilities will be promptly addressed.

## Security Fixes

### Stored Cross-Site Scripting via Template Placeholders in the Comment Author Name (Fixed in 7.6.74)

**Severity:** High (CVSS 7.1)
**Type:** Stored Cross-Site Scripting (XSS)
**Affected Component:** Front-end comment rendering (`themes/default/class.WpdiscuzWalker.php`) and the guest Name field
**Affected Versions:** Up to and including 7.6.73.

**Description:**
The comment renderer collected all template placeholders (`{AVATAR}`, `{AUTHOR}`, `{TEXT}`, ...) and their values into two arrays and resolved them with a single array `str_replace()`. PHP applies such replacements one pair after another over the whole string, so a placeholder that appeared inside an already inserted value was expanded again. The avatar markup, whose `alt` attribute holds the escaped author name, was inserted before the comment body. An author name containing the literal text `{TEXT}` therefore had the raw comment HTML expanded into the `alt` attribute after escaping had already run. A body consisting of a straight quote and event-handler text inside a `<code>` element passes `wp_kses` and is not touched by `wptexturize`, so the quote closed the attribute and `onerror`/`onload` became live attributes on the avatar `<img>`.

The name could be supplied by an unauthenticated guest through the Name field, or by any registered user through the display name of their profile. The script ran without interaction for moderators and administrators viewing the comment while it was pending, and for every visitor once it was approved.

**Fix Applied:**
1. The renderer now resolves placeholders with `strtr()` and a single map, which performs one pass and never rescans inserted values. The comment templates themselves are unchanged, so custom layouts in child themes keep working.
2. Added `WpdiscuzHelper::neutralizeTemplateBraces()`, which encodes `{` and `}` as HTML entities. The guest Name field passes through it before the name is stored.
3. The name shown in the "last edited by" note, the only user value concatenated into the template before the final pass, goes through the same helper.
4. The parent author name in the "Reply to" block is now escaped with `esc_html()`, matching the author name in the comment header.

**Recommended Action:**
Update to 7.6.74. No settings change or data migration is needed: comments whose author name already contains braces are rendered safely by the new renderer. Sites that were on an affected version may want to review pending and approved comments whose author name contains `{` or `}`.

**Files Modified:**
- `themes/default/class.WpdiscuzWalker.php` - Single-pass placeholder substitution; escaped the parent author name and the "last edited by" name
- `utils/class.WpdiscuzHelper.php` - Added `neutralizeTemplateBraces()`
- `forms/wpdFormAttr/Field/DefaultField/Name.php` - Braces in the guest name are encoded before the name is stored

**Credit:**
Reported through Patchstack.

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
