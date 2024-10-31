=== rTwit ===
Contributors: RyanNutt
Tags: twitter, tweet, social networking
Requires at least: 3.0.0
Tested up to: 3.0.4
Stable tag: 0.1.1

Quickly embed any twitter feed into a post or page.

== Description ==

rTwit adds a short code that allows you to embed any Twitter feed into posts
and pages on your WordPress site.

This plugin came about from my need to embed tweet from software authors
on a page describing the software.

Requests are cached for 30 minutes, by default, so that subsequent page
views can load from cache rather than having to reload and reparse the
Twitter feed.  Links are set to open in a new window, although that is
optional.

== Installation ==

1. Upload the `/rtwit/` folder to your `/wp-content/plugins/` folder
1. Activate the plugin through the Plugins menu in WordPress
1. Set any options

= Usage =

Place the short code [rtwit] in a post or page to display the feed from your
default account.

You can also add the following tags to the short code.

* account - The account to display
* count - How many tweets to display

As an example, to display the 25 most recent tweets from Bob123 you would
enter [rtwit account=Bob123 count=25]

== Changelog ==

= 0.1.1 =
* Just moving plugin to new home page, so no changes. Nothing to update. 

= 0.1 =
1/18/2011 - First public release.

== Screenshots ==
1. Options screen for rTwit

== Frequently Asked Questions ==

= Can I see an example =
Ok, so nobody has asked this yet.  But there is a demo at [AppleLibre](http://www.applelibre.com/2011/01/keeping-track-of-fleeting-thoughts-with-evernote/)

== Changelog ==

= 0.1 - Jan 18, 2011 =
First release

== Upgrade Notice ==
= 0.1 =
First release, so nothing to upgrade :)