# Small PHP GitLab CD
This project contains some simple scripts to deploy gitlab artifacts which are created by builds.

This project is still in development but works so far ([See Coming Features](#coming-features)).

## Requirements
* You need `php >= 5.6`.
    * Optionally, `composer` is required for creating project with composer.
* `rsync` is required to move files from cache to your defined target ([See Configuration](#configuration)).
* You need write permission to the `Logs` and `secret_token` directories.

## Installation
* Clone this repository: `git clone https://github.com/jdoubleu/small-php-gitlab-cd.git`,
* Use [Composer](https://getcomposer.org/) to create a new project: `composer create-project jdoubleu/small-php-gitlab-cd`

## <a name="configuration"></a>Configuration
A `config.example.json` can be found in `Config/` dir and should be renamed to `config.json`.

Configure this file to your needs.

The `secret_token` property represents a private token such like an api key and should be added as GET parameter to your url.

## Usage
Go into your GitLab instance (or to [gitlab.com](gitlab.com)), go to the settings of your project and create a Webhook.
Select only `Build events` and enter the url with appended value of `secret_token` in `URL` field.

For example: `https://cd.example.com/deployment/?secret_token=AverySecretTokenOnlyYouShouldKnow`

Now you're done! Eevery time a build happens your script is called. Only on fitting and successful builds your script tries to deploy
the artifacts.

## <a name="coming-features"></a>Coming Features
I will - and you are welcome to contribute - restructure the whole scripts to be more simplified and procedural such like [simple-php-git-deploy](https://github.com/markomarkovic/simple-php-git-deploy).

## Contribute
You are welcome to contribute to this project.

Please checkout `develop` branch and create a feature branch out of it. If you are finished create a PR on `develop`.

Thanks alot!