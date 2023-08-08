# WebTigerJython Adaption for Cloud

[wtj.temperli.io](https://wtj.temperli.io)

## Cloud-Adaption

- Save TigerJython-Code directly on the server
-- Each code gets its own URL
-- Example: [wtj.temperli.io/3dfd01](https://wtj.temperli.io/3dfd01)
- Adopt saved Programs and generate a new version of the program
-- Mark the difference of the two code-versions
-- Example: [wtj.temperli.io/3dfd01/c44771](https://wtj.temperli.io/3dfd01/c44771)

## Update Base: WebTigerJython

In the background, this adaptation is based on the original version from the [ABZ](https://abz.inf.ethz.ch/) at the ETH [webtigerjython.ethz.ch/](https://webtigerjython.ethz.ch/).

    $ npm run wtj-update

Run the command to update the original source of webtigerjython directly from the original source.

---

## Lumen by Laravel

The PHP-Framework in the background is based on Lumen by Laravel.

### Create new Model

1. Add Model to `./app/Model`
2. Create migrations: `$ php artisan make:migration create_wtj_tokens_table`
3. Update the migrations-file manually
4. Run migrations: `$ php artisan migrate`
