#Logout on Tab / Browser Close#

I was working with a client who wanted users to be logged off when they were idle too long or when they closed their browser or their tab.  I liked the looks of [Idle User Logout](https://wordpress.org/plugins/idle-user-logout/).  It handles the idling part very well.  Unfortunately, it doesn't handle the tab close - if Alice closes the tab, Bob could come along and open a new tab and be logged in as Alice.

I wrote a plugin which utilizes the Heartbeat API to update an option in the options table with that user's ID, and a cron job to look at that option and boot anyone whose last check-in time was too long ago.