# Schubert Genealogy

TYPO3 extension for importing and rendering the Franz Schubert genealogy from a GEDCOM export.

## Import source

The GEDCOM export is stored at:

```text
Resources/Private/Import/franz-schubert.ged
```

The large original GedZip file is intentionally ignored by Git.

## Installation

From the project root:

```bash
composer require schubertliederplugin/schubertgen:@dev
ddev exec typo3 extension:setup
ddev exec typo3 schubertgen:import-gedcom --replace --pid=0
```

Use a dedicated storage PID instead of `0` if the genealogy records should live on a storage folder.

The import also reads media from the GedZip file and stores the images in the default FAL storage folder `/schubertgen/`. If needed, pass the ZIP path explicitly:

```bash
ddev exec typo3 schubertgen:import-gedcom --replace --pid=156 --media-zip='/var/www/html/packages/schubertgen/Franz Schubert.zip'
```

Use `--skip-media` to import only database records without touching FAL.

## Frontend

Add the `Schubert Genealogy` plugin to a page and include the static TypoScript. The default start person is Franz Peter Schubert (`96255408`) and can be changed through TypoScript:

```typoscript
plugin.tx_schubertgen.settings.defaultPersonExternalId = 96255408
```
