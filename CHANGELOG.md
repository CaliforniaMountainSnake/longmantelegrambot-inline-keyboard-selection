# Changelog
The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
### Changed
### Deprecated
### Removed
### Fixed
### Security


## [3.0.2] - 2019-12-14
### Changed
- The results of selections now pass in the parameter of success and back callbacks.
- save_data_callback is optional now.

## [3.0.1] - 2019-12-14
### Security
- Updated composer dependencies and fix vulnerabilities founded by GitHub.

## [3.0.0] - 2019-12-14
### Changed
- The signature of MultipleSelection has been changed. Now there are three callbacks: save_data_callback, success_callback and optional back_callback.

## [2.1.2] - 2019-11-15
- Fixed a bug with results' storage in MultipleSelection.

## [2.1.1] - 2019-11-15
### Fixed
- Fixed a bug with preselected values in MultipleSelection.

## [2.1.0] - 2019-11-13
### Added
- Added the support of preselected values for MultipleSelection. The signature of MultipleSelection::multipleSelection() has been changed.

## [2.0.1] - 2019-11-10
### Fixed
- Fixed a small bug in MultipleSelection.

## [2.0.0] - 2019-11-05
### Changed
- The major classes refactoring. MultipleSelection and OneSelection now have unified main method signatures. The selections now are "clear" functions, left nothing data in conversation after themselves and returns result in the parameter of the callback.

## [1.0.0] - 2019-10-14
### Added
- Added the traits that allow to select one and multiple values using inline keyboard.
