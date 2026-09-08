# SB118 Private Sites

A WordPress **must-use plugin** that enforces per-site membership on private sub-sites of a
multisite network. If a sub-site is marked private (`blog_public = 0`), only users who have
been explicitly added to *that* sub-site can see it — over the web, the REST API, or feeds.

Built and used by [StarBase 118](https://www.starbase118.net) to keep staff and training
areas of our WordPress multisite genuinely private. Shared here because it's a small, clean
solution to a problem a lot of multisite operators hit.

## Why this exists

WordPress's built-in "Discourage search engines" toggle (`blog_public = 0`) only sets a
`noindex` hint — it doesn't actually *block* anyone. Popular "private site" plugins typically
gate the front end but leave two side doors wide open:

- the **REST API** (`/wp-json/…`) happily serves pages and content, and
- **RSS/Atom feeds** (`/feed/`) stream posts to anyone who asks.

This plugin closes all three doors, and goes one step further: being logged into the network
isn't enough — a user must be a **member of the specific sub-site**.

## What it does

On any sub-site where `blog_public = 0`, for a visitor who is **not a member of that
sub-site**:

| Vector | Behaviour |
| --- | --- |
| REST API (`/wp-json/*`) | `401 Unauthorized` |
| RSS / Atom feeds (`/feed/`, `/feed/atom/`, …) | `403 Forbidden` |
| Direct page access | Logged-out users go to `wp-login.php`; signed-in non-members get a `403` with their account identity and a nonce-protected sign-out link |
| Feed `<link>` tags in `<head>` | removed |

**Access rule:** logged in **and** a member of the current sub-site. Network **super admins
always have access**. Public sub-sites (`blog_public = 1`) are completely unaffected, and the
login, cron, AJAX, and activation endpoints are always allowed through.

## Install

This is a **must-use plugin** — it loads automatically on every site in the network and
can't be deactivated from the admin UI.

1. Copy `sb118-private-sites.php` into your network's `wp-content/mu-plugins/` directory
   (create the folder if it doesn't exist).
2. That's it — there's no activation step and no settings page.
3. Mark a sub-site private under **Settings → Reading → Search engine visibility**
   (this sets `blog_public = 0`), and add the users who should have access under
   **Users** for that sub-site.

## Requirements

- WordPress **multisite** 5.0+
- PHP 7.2+

## How it works

The whole plugin is a handful of WordPress hooks (`rest_authentication_errors`, the `do_feed_*`
actions, `template_redirect`, and `wp`). Each one first checks `blog_public`, then checks
membership via core's `is_user_member_of_blog()`. No database tables, no options, no admin UI.
Read [`sb118-private-sites.php`](sb118-private-sites.php) — it's short.

## Checks

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/denial-message.php
```

## License

[GPL-2.0-or-later](LICENSE), the same license as WordPress itself.

---

<sub>Maintained by **StarBase 118** — a fan-run *Star Trek* play-by-email RPG since 1994. We
write collaborative fiction together across a fleet of starships. If that sounds like your
kind of thing, [come write the next chapter →](https://www.starbase118.net)</sub>
