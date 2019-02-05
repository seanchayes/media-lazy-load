# Media Lazy Load

License: GPLv3

License URI: http://www.gnu.org/licenses/gpl-3.0.html

A plugin to reduce initial page bandwidth for website images; uses the browser intersection API to load images and iframes when coming into view.

## Description

Based on the best practice software to detect and lazy load media this plugin updates the markup in your images and iframes when rendering your page.

* Reduce your site bandwidth on initial load.
* Then the plugin loads images and iframes to be ready for display in the browser viewport
* Works with images on category / archive pages.
* Works with avatars
* Works with <video> tag
* Works with images inside galleries - only loading in the images when they are needed.
* With this plugin enabled it can contribute to improved results in Lighthouse audits or audits run from https://web.dev/
* Image tag markup is handled when it is generated with WordPress functions

I use the [lazysizes lazy load library](https://github.com/aFarkas/lazysizes) - thank you

This lazy load library came as a recommendation. I was running audits and the audit results referred to this library. You can find out more from [web.dev](https://web.dev/fast/use-lazysizes-to-lazyload-images)

## Installation
You can clone this repo to your ```plugins``` folder and activate from your dashboard.
 
**or**

First, in this repository, click on "Clone or Download" and then "Download Zip".
Then follow the regular WordPress instructions below.

#### Using The WordPress Dashboard

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'media-lazy-load'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

#### Uploading in WordPress Dashboard

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `media-lazy-load.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

#### Using FTP

1. Download `media-lazy-load.zip`
2. Extract the `media-lazy-load` directory to your computer
3. Upload the `media-lazy-load` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard


#### Frequently Asked Questions
Does it modify and save my markup?
No, it only adjusts the display during page generation when displaying your content

#### Screenshots

#### Changelog

##### 0.2.1
* Readme updates
##### 0.2
* Handling Gutenberg cover image, Gutenberg gallery support, checking for customizer, REST request
##### 0.1
* First version, adding lazysizes script, parsing markup, adding class to images and iframe based media

