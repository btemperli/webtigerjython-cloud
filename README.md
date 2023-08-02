# WebTigerJython Adaption for Cloud

by btemperli

[wtj.temperli.io](https://wtj.temperli.io)

## Cloud-Adaption

- Save TigerJython-Programs directly on the server
- Adopt saved Programs and generate a new version of the program
-- Mark the difference of the two versions

## Update WebTigerJython

In the background runs the original webtigerjython from [webtigerjython.ethz.ch/](https://webtigerjython.ethz.ch/).

    $ npm run wtj-update

Run the command, to update the original webtigerjython directly from its source.

---

## Lumen by Laravel

The PHP-Framework in the background is based on Lumen by Laravel.

[![Build Status](https://travis-ci.org/laravel/lumen-framework.svg)](https://travis-ci.org/laravel/lumen-framework)
[![Total Downloads](https://poser.pugx.org/laravel/lumen-framework/d/total.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Stable Version](https://poser.pugx.org/laravel/lumen-framework/v/stable.svg)](https://packagist.org/packages/laravel/lumen-framework)
[![License](https://poser.pugx.org/laravel/lumen-framework/license.svg)](https://packagist.org/packages/laravel/lumen-framework)

### Create new Model

1. Add Model to `./app/Model`
2. Create migrations: `$ php artisan make:migration create_wtj_tokens_table`
3. Update the migrations-file manually
4. Run migrations: `$ php artisan migrate`
