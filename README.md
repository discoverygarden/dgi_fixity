# Fixity

## Introduction

Perform periodic fixity checks on selected files.

This module defines a new content entity type `fixity_check`. This entity is
used as an audit trail for fixity checks performed on a related `file` entity.
Wherein the revisions of the `fixity_check` record the results of previous 
checks against that `file` entity.

This modules requires and enforces the following constraints on `fixity_check` 
entities:

- **Must** be related to a `file`
- `file` relations **must** be unique
- `file` relation **cannot** be changed after creation
- `performed` and `state` properties **cannot** be modified after creation.

Users with the permission `Administer Fixity Checks` can:

- Manually perform checks
- Manually remove `fixity_check` entities and their revisions
- Manually mark files as requiring periodic checks
- Generate `fixity_check` entities for all previously existing files

Users with the permission `View Fixity Checks` can:

- View fixity audit log of Media entities

A `cron` hook is setup to automatically mark files as _requiring_ periodic
checks. As well as performing those checks on a regular basis. Email 
notifications can be configured to alert the selected user of the status
of all performed checks on a regular basis or only when an error occurs.

## Requirements

This module requires the following modules/libraries:

* [filehash]

## Configuration

The module can be configured at `admin/config/fixity`.

## Drush

A number of drush commands come bundled with this module.

```bash
$ drush dgi_fixity:clear --help
Sets the periodic check flag to FALSE for all files.
```

```bash
$ drush dgi_fixity:generate --help
Creates a fixity_check entity for all previously created files.
```

```bash
$ drush dgi_fixity:check --help
Perform fixity checks on files.

Options:
  --fids[=FIDS] Comma separated list of file identifiers, or a path to a file containing file identifiers.
                The file should have each fid separated by a new line. If not specified the modules settings
                for sources is used to determine which files to check.
  --force       Skip time elapsed threshold check when processing files.
```

## Installation

Install as usual, see [this][install] for further information.

Additionally after this module is first enabled, you will need to generate
`fixity_check` entities for all pre-existing `file` entities. This does not
require that the checks are performed, only that one `fixity_check` entity
exists for every applicable `file` entity in the system.

This can be done with `drush`:

```bash
drush dgi_fixity:generate
```

Or via the admin form on the page `admin/config/fixity/generate`.

## Troubleshooting/Issues

Having problems or solved a problem? Contact [discoverygarden].

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden]

## Development

If you would like to contribute to this module create an issue, pull request
and or contact [discoverygarden].

## License

[GPLv2][gplv2]

[discoverygarden]: http://support.discoverygarden.ca
[filehash]: https://www.drupal.org/project/filehash
[gplv2]: http://www.gnu.org/licenses/gpl-2.0.txt
[install]: https://drupal.org/documentation/install/modules-themes/modules-8