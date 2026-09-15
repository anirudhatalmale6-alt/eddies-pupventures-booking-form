=== Eddie's Pupventures — Booking Agreement ===
Version: 1.1.0

The paper booking agreement, rebuilt as a mobile-friendly online form.


INSTALLING
----------
1. WordPress dashboard → Plugins → Add New → Upload Plugin
2. Choose eddies-pupventures-booking.zip → Install Now → Activate

That's it. Activating creates a page called "Booking Form" at
yoursite.co.uk/booking-form/ with the form already on it, and adds a
"Booking Forms" item to your dashboard menu.


WHERE EVERYTHING LIVES
----------------------
Booking Forms                  every signed agreement, newest first
Booking Forms → Settings       your email address, the price list,
                               whether clients get their own copy
Booking Forms → Export         download the lot as a spreadsheet

Open any booking to see the whole form, the signature, and a
"Print / save as PDF" button.


WHEN SOMEONE SIGNS
------------------
* The form is saved in WordPress first, so nothing is ever lost even if
  the email fails.
* You get an email with the signed agreement attached as a PDF, plus a
  link to the record.
* They get their own copy, PDF included.
* Home access details (key safe codes) are encrypted in the database and
  are deliberately left out of every email. They only appear when you're
  logged in to your own dashboard.


CHANGING THE PRICES
-------------------
Booking Forms → Settings. The price list on the form, and the estimate a
client sees before signing, both follow whatever you put there.


KEEPING IT PRIVATE
------------------
The booking page is never listed in your menu, is hidden from Google and
is left out of your site's own search. Under Settings you can also switch
on a private link, so the page only opens for people who have the link
you sent them. Everyone else sees a short note asking them to get in
touch first.


PUTTING THE FORM SOMEWHERE ELSE
-------------------------------
Add this shortcode to any page:

    [pupventures_booking_form]


IF YOU EVER NEED TO DELETE SOMEONE'S DATA
-----------------------------------------
Open the booking, "Move to bin", then empty the bin. That permanently
removes their record, which is what UK GDPR expects when someone asks to
be forgotten. Print a copy first if you need to keep one.
