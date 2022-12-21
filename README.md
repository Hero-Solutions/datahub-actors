# OAI-PMH API test application

## Summary

This is a n application to harvest metadata from an [OAI-PMH](https://www.openarchives.org/pmh/) API, more specifically the [Datahub](https://github.com/thedatahub/Datahub). It makes use of the PHP library [caseyamcl/phpaoipmh](https://github.com/caseyamcl/phpoaipmh).

Configuration of the API URL and the XPath expressions to find relevant XML data is found in ```config/datahub-actors.yaml```.

## Requirements

- PHP >= 7.2.5
- Composer >= 2.0

## Installation

Clone this repository
```
git clone git@github.com:Hero-Solutions/datahub-actors.git
```

Install through composer:
```
composer install
```

## Usage

You can run the application through the following command:
```
php bin/console app:fetch-actors
```

This will fetch the relevant data from the OAI-PMH endpoint and output it to a file.
