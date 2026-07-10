=== Tournament Entry Form ===

Adds a "Tournament" custom post type, links each tournament to a Contact Form 7
form, and records every submission in a custom database table
(wp_submissions) that you can browse in the admin.

== Installation ==

1. Upload the "tournament-entry-form" folder to /wp-content/plugins/
2. Activate the plugin through the "Plugins" menu in WordPress
   (this creates the `wp_submissions` table automatically)
3. Make sure Contact Form 7 is installed and active
4. Go to Tournaments > Add New, write your tournament, and in the
   "Tournament Entry Form" box on the right, pick the CF7 form to use
5. The chosen form is automatically shown at the bottom of the tournament's
   page. Publish the tournament and you're done.
6. View entries any time under Tournaments > Submissions (with search,
   sorting, delete, and a CSV export button).

== CF7 field names ==

The plugin reads these field names out of the CF7 submission by default:

- Name:            your-name        (or "name")
- Email:            your-email       (or "email")
- Discord username: discord-username (or "discord" / "your-discord")
- Friend code:      friend-code      (or "friendcode" / "your-friend-code")

If your CF7 form uses different field names, open
tournament-entry-form.php and edit the `tef_extract_field()` calls inside
`tef_capture_submission()` to match your field names (e.g. [text* your-name]
in the CF7 form editor).

== Notes ==

- Submissions are only linked back to a specific tournament (the
  "Tournament" column in the submissions list) when the form is displayed
  on that tournament's own page, which happens automatically. If you embed
  the same form elsewhere, the tournament link will be blank but the
  submission is still recorded.
- The submissions table is NOT deleted on plugin deactivation or
  uninstall, so your data is safe if you temporarily disable the plugin.
