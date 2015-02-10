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

Sample calls
============

Site information

```
curl 'http://yoursite.com/webservice/rest/server.php?moodlewsrestformat=json' -H 'Pragma: no-cache' -H 'Origin: file://' -H 'Accept-Encoding: gzip,deflate,sdch' -H 'Accept-Language: es,en;q=0.8,de-DE;q=0.6,de;q=0.4,nb;q=0.2' -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1798.0 Safari/537.36' -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' -H 'Accept: application/json, text/javascript, */*; q=0.01' -H 'Cache-Control: no-cache' -H 'Connection: keep-alive' --data 'wsfunction=core_webservice_get_site_info&wstoken=yourtoken' --compressed
```

Python
```
>>> import requests
>>> payload = {"wsfunction": "core_webservice_get_site_info", "wstoken": "yourtoken", "moodlewsrestformat": "json"}
>>> r = requests.post("http://yoursite.com/webservice/rest/server.php", payload)
>>> print(r.text)
```