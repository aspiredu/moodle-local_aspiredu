AspirEDU Integration
===========================

Local plugin for Moodle

Installation
===========

There are two methods for installing the plugin:

Method 1:

Site administration / Plugins / Install plugins (This method requires write permission in the /local directory in your Moodle installation)

Method 2:

Download the plugin from: https://moodle.org/plugins/view/local_aspiredu

Unzip the plugin inside the /local/ directory in Moodle

A new directory named aspiredu will be created /local/aspiredu

Go to Administration -> Notifications

In both cases you will be requested to enter the product URL and key.

Enabling Web Services
=============================

1 Create a new user to be used as "Web Services User", in Site administration / Users / Permissions / Assign system roles assign a role with enough privileges (manager or admin). Ensure that the role has the capabilities in Define Roles:  webservice/rest:use
2 Administration / Advanced features. Enable web services
3 Administration / Plugins / Web Services/ Manage protocols. Enable REST depending your client implementation
4 Administration / Plugins / Web Services / External Services. Go to Authorized users for the "AspirEDU Service" service
5 Add there the user created in step 1
6 Administration / Plugins / Web Services / Manage tokens. Create a token for the user created in step 3 for the service "AspirEDU Services"
7 The token created is used as an authentication token in your client

Sample calls
============

Site information

```
curl 'http://yoursite.com/webservice/rest/server.php?moodlewsrestformat=json' --data 'wsfunction=core_webservice_get_site_info&wstoken=yourtoken' --compressed
```

Python
```
>>> import requests
>>> payload = {"wsfunction": "core_webservice_get_site_info", "wstoken": "yourtoken", "moodlewsrestformat": "json"}
>>> r = requests.post("http://yoursite.com/webservice/rest/server.php", payload)
>>> print(r.text)
```