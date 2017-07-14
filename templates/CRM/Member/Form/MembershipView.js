CRM.$(function($) {
    // FIXME: nth-child is not the most reliable, but we have no other unique selector we can use here
    $('[id^="membership_general"] div.crm-accordion-body table:nth-child(3) td:last').wrap('<a href="CONTRACT_FILE_DOWNLOAD" target="_blank">');
});
