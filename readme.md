# WooCommerce Gateway for Snapplify Pay

## Overview

This plugin is a payment gateway for WooCommerce. Wordpress and WooCommerce are required to use it.

## Usage

### Installation
The plugin may be installed manually or automatically (once published to the WordPress directory) as with any other WordPress plugin.

### Snapplify Pay
A Snapplify Pay Merchant account is needed to use this plugin and credentials supplied by Snapplify Pay will be required for the setup of the plugin in the WordPress administration panel.

The following credentials are required in order to use the plugin:
* Public Key
* Secret Key

*NOTE: credentials are saved separately for Sandbox and Live environments. Make sure you've entered your credentials once you've chosen to enable Production Mode.*

### Testing, Troubleshooting and Launch/Going Live

The plugin has options that will enable/disable it entirely or for different use cases.

By default the plugin will be in Sandbox mode. Should your store be live already and accessible to the public you may restrict who can see and use this gateway for payments to users with Admin and Shop Manager roles allowing you to test the plugin thoroughly before launching it.

You may also use the Admin & Shop Manager restriction to troubleshoot problems; this will allow you to test & fix issues in a live environment whilst disabling the plugin for customers.

*Recommended stages to go with enabling this plugin when enabling it for Production is to first test with only Admin & Shop Managers having access, then once satistfied that all is working as expected; disabling the Admin/Shop Manager restriction.*

## Development

This plugin is released using [SemVer](https://semver.org/).<br>
To create a distributable version of this plugin you can use the `makedist.sh` bash script found in `/dev`. The script will prompt you for a version number and will create a WordPress installable zip archive for you. (if new files and folders are added to this project that are required for the final distribution the script will need to be updated accordingly).<br>
The `readme.txt` can be modified & updated based on the [WordPress guidelines here](https://wordpress.org/plugins/developers/#readme).
