AspirEDU Integration
===========================

Local plugin for Moodle

Installation
===========

Unzip the plugin inside the /local/ directory in Moodle

A new directory named aspiredu will be created /local/aspiredu

Go to Administration -> Notifications

Enabling Web Services
=============================

1 Administration / Advanced settings. Enable web services
2 Administration / Plugins / Web Services/ Manage protocols. Enable SOAP or REST or XMLRPC depending your client implementation
3 Create a new user to be used as "Web Services User", in Site administration / Users / Permissions / Assign system roles assign a role with enough privileges (manager or admin). Ensure that the role has the capabilities in Define Roles:  webservice/soap:use or webservice/rest:use or webservice/xmlrpc:use
4 Administration / Plugins / Web Services / External Services. Go to Authorized users for the "AspirEDU Service" service
5 Add there the user created in step 3
6 Administration / Plugins / Web Services / Manage tokens. Create a token for the user created in step 3 for the service "AspirEDU Services"
7 The token created is used as an authentication token in your client
