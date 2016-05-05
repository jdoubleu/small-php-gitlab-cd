# Small PHP GitLab CD v0.2
This project contains a simple script to deploy GitLab artifacts.

Build artifcats can be build using the GitLab CI ([more here](http://doc.gitlab.com/ce/ci/build_artifacts/README.html)).

This project was heavily inspired by [simple-php-git-deploy](https://github.com/markomarkovic/simple-php-git-deploy).

## Requirements
* You need `php >= 5.6`.
    * Optionally, `composer` is required for creating project with composer.
    * Optionally, `sendmail` is required to send notifications on errors.
* `curl` is required to download artifacts from gitlab using the api,
* `unzip` is required to extract the artifacts and finally
* `rsync` is required to move files from cache to the defined target.
* You also need read and write permissions to all defined directories ([See Configuration](#configuration)).

## Installation
* Clone this repository: `git clone https://github.com/jdoubleu/small-php-gitlab-cd.git` or
* Use [Composer](https://getcomposer.org/) to create a new project: `composer create-project jdoubleu/small-php-gitlab-cd`

## <a name="configuration"></a>Configuration
Copy the `deploy-config.example.php` to `deploy-config.php`. The deploy script looks for a file with its name appended with "config" so
you can create as much configurations as you want.

Have a look at the comments in the config file(s)!

## Usage
### For WebHook's
Go into your GitLab instance (or to [gitlab.com](gitlab.com)), then go to the settings of your project and create a Webhook.
Select only `Build events` and enter the url with appended value of `secret_token` in `URL` field.

For example: `https://cd.example.com/deployment/?secret_token=AverySecretTokenOnlyYouShouldKnow`

Now you're done! Every time a build happens your script is called. Only on fitting and successful builds your script tries to deploy
the artifacts.

### From CLI
Run the deploy script manually using your php interpreter like this:
```
/usr/bin/php path/to/deploy.php -p 1 -b 10
```
where
* -p determines the project id (optional if no project id is given in config) and
* -b the build id

## Contribute
You are welcome to contribute to this project. Either in creating issues or submitting code.

Please checkout `develop` branch. If you want to introduce a new feature you should create a feature
branch out of the `develop` branch.
If you are finished just create a PR on `develop`.

Thank You!