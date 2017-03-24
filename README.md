# WordPress API Framework

A simple API plugin for WordPress, that serves more as a framework for those
wanting to craft their own RESTful API service.

The plugin has 2 endpoints set up; one for authenticating users and one for
posting to the blog. Access to these and endpoints you add can be
toggled easily through the plugin's settings page. Endpoints can also be marked
as secure, requiring a JSON Web Token for authorization.

## Using The API

Once you activate the plugin, a new rewrite rule will be added to your blog. To
use the API, append this to your blog URL:

    /api/v1/

The endpoint you wish to access should be added at the end, for example:

    http://mywebsite.co.uk/api/v1/login

Responses to and from the API are in JSON format.

## Authenticating Users

Endpoints marked as secure require a JSON Web Token. You can use the **login**
endpoint to generate a token. This token should then be added to the **Authorization**
header in your request with **Bearer** as a prefix. For example:

    Authorization: Bearer eyeyeyeyey.bububbububu.cedededede

For more information on JSON Web Tokens, see here: [https://jwt.io/introduction/](https://jwt.io/introduction/)

## Endpoints

#### Login

    /api/v1/login

This returns a JSON Web Token when valid credentials have been sent.

###### POST

    {
        "username": "joebloggs",
        "password": "badpassword1234"
    }

###### RESPONSE

    {
        "token": "eyeyeyeyey.bububbububu.cedededede"
    }

#### POST

    /api/v1/post

This endpoint creates a new post on the blog.

###### POST [required]

    {
        "post_title": "My First Post!",
        "post_content": "All work and no play makes Jack a dull boy."
    }

###### POST [optional]

    {
        "post_content_filtered": "",
        "post_excerpt": "blah",
        "post_status": "publish",
        "post_type": "post",
        "post_date": "Y-m-d h:i:s",
        "post_password": "",
        "post_name": "my_first_post",
        "post_parent": 0,
        "menu_order": 0,
        "tax_input": array,
        "meta_input": array
    }

###### RESPONSE

    {
        "post_id": 26
    }

## Customizing

The plugin adds a new options page where you customize various settings for the
API.

#### Secret Key

This password is used to sign and verify JSON Web Tokens.

#### Expire Time

Generated tokens will expire once this amount of time has passed.

#### Available Endpoints

Here you can toggle endpoints and make them secure. Endpoints that are marked as
secure require a token.
