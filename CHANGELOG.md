# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.1] - 2020-11-01
### Added
- Support for front-token

## [1.5.0] - 2020-09-10
### Added
- Support for CDI 2.3 and FDI 1.2
- Remove compatibility with CDI 1.0

## [1.4.0] - 2020-08-10
### Addition
- `getCORSAllowedHeaders` function to make CORS handling easier
- Compatibility with CDI 2.2

### Fixes:
- If CDI is 1.0, then do not add access token in `getSession` function

## [1.3.0] - 2020-07-02
### Addition
- Support for API key
- Compatibility with CDI 2.1

## [1.2.0] - 2020-06-17
### Changes
- config changes and code refactor

## [1.1.0] - 2020-06-04
### Breaking changes
- Session cookies are now url-encoded. This will cause older cookies to not be accepted anymore. 

## [1.0.3] - 2020-05-19
### Changes
- Support for older versions of laravel >= 5.7

## [1.0.0] - 2020-05-04
### Added
- Middleware and error handling support
- Support for CDI 2.0
### Changes
- Code refactor