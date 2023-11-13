![Build Status](https://github.com/aspiredu/moodle-local_aspiredu/actions/workflows/master.yml/badge.svg?branch=dev)

AspirEDU Integration
===========================

A local plugin for Moodle which provides a set of web services, a web service function and an LTI gateway to power the AspirEDU product suite.

Installation
===========

There are two methods for installing the plugin, in both cases you will need to have your product URL and key.

Method 1 - upload via Moodle UI:

Login as a site admin and navigate to:\
```Site administration > Plugins > Install plugins```\
This method may not be available depending on your hosting solution as it requires the web server process to have write permission in the /local directory in your Moodle installation.

Method 2:

Download the plugin from: https://moodle.org/plugins/view/local_aspiredu

Extract the contents into Extract the contents into /wwwroot/local then visit admin/upgrade.php at which point you will be asked to enter the product URL and key.

Enabling and configuration Web Services
=============================
- Access ''Administration > Site administration > Advanced features''
  - Check 'Enable web services' then click 'Save Changes'
- Access ''Administration > Site administration > Server > Web services > Manage protocols''
  - Enable the REST protocol
- Access ''Site administration > Users > Accounts > Add a new user''
  - Create a new user which will be used by the AspireEDU platform to make the web service calls, the details are not important but make a note of the username.
- To configure the role
  - Access ''Site administration > Server > Web Services > External Services'' and click on ''Functions in the AspirEDU Services'' row.
  - In a new browser tab Access ''Site administration / Users / Permissions / Define roles''
  - Click ''Add a new role'' check ''Use role or archetype'' is set to ''None'' and continue
  - On the next form:
    - Choose an appropriate name for the role
    - 'Context types where this role may be assigned' - Check the 'System' option
    - In the list of capabilities, check the ''Allow'' box for ''Use REST protocol'' and then for each capablity listed in the ''Required capabilities'' column on the table in the previous browser window.
    - Click ''Create this role'''
  - Access ''Site administration / Users / Permissions / Assign system roles'''
    - Assign the role that was just created to the user that was just created. 
- Administration / Plugins / Web Services / External Services
  - Go to Authorized users for the "AspirEDU Service"
   service 
  - Add there the user created in step 1 
- Administration / Plugins / Web Services / Manage tokens
  - Click ''Create token''
  - Select the user that was previously created and the 'AspirEdu' service
  - Click ''Save changes''
  - Make a note of the token that was created and use it to compelete the configuration of the platform

License
===========
Copyright (c) 2023 AspirEDU (https://aspiredu.com)

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.