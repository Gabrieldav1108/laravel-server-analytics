# Changelog

All notable changes to this project are documented in this file. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Page view recording through the `RecordPageView` middleware, prepended to
  the `web` group: route pattern, HTTP status and response time.
- Login, logout and failed login recording from Laravel's authentication
  events.
- `Analytics::action()` for business actions, with the model type and key.
- `ServerAnalytics::resolveActorUsing()` to tag events with tenant and user.
- Redis queue and the `server-analytics:flush` command, scheduled every
  minute while the endpoint and the token are set.
