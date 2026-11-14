# Moallemyar — Persian Learning Portal

A Persian, right-to-left WordPress learning portal for teacher and parent training. It combines a custom plugin (version **0.10.0**) with a companion theme (version **1.0.0**) and integrates the official H5P plugin for interactive lessons.

This repository is a source portfolio and pilot implementation, not evidence of a completed school deployment. It contains application code and interface assets only: no learner records, identity numbers, phone lists, database exports, school content or credentials.

## What it demonstrates

- Separate teacher and parent learner roles, course audience rules and enrollment checks.
- Role and identity lookup followed by an SMS one-time code sent to the registered phone.
- Course prerequisites, ordered lessons, release days and optional completion gates.
- H5P upload, editing, playback and export through the official H5P integration.
- Elapsed and active learning time, progress reports and final response capture when supported by the H5P content type.
- A responsive Persian interface and a matching public-facing WordPress theme.

Parents take their own assigned courses in this version. Child records and child-performance dashboards are **not implemented**. Browser-reported activity and xAPI completion are learning signals, not an anti-cheating system.

## Repository layout

```text
plugin/     Custom Moallemyar WordPress plugin
  includes/ Administration, authentication, REST API, H5P and data model
  templates/ Login and application screens
  assets/   CSS, JavaScript and interface illustrations
theme/      Companion WordPress theme
```

## Installation

The source declares WordPress 6.4+ and PHP 8.0+ requirements. Use a supported WordPress/PHP combination for your actual environment and test it before deployment.

1. Install WordPress in an isolated development environment.
2. Install and activate the official **H5P – Create and Share Rich Content and Applications** plugin separately. H5P and its content libraries are not bundled here.
3. Copy `plugin/` to `wp-content/plugins/moallemyar/` and activate Moallemyar.
4. Optionally copy `theme/` to `wp-content/themes/moallemyar-theme/` and activate the theme.
5. Create and publish a course, set its audience, then add lessons and H5P content you have permission to use.
6. Create test learner accounts through the administration interface and assign course membership. Use synthetic data in development.
7. Configure your SMS provider and test login and role restrictions before granting real users access.

Activation creates the portal pages. The shared login is `/teacher-login/`; the role-specific destinations are `/teacher-portal/` and `/parent-portal/`.

## SMS configuration

The `development` SMS driver returns a test code only when WordPress reports a `local` or `development` environment. It must not be used as a production authentication service.

The `generic_json` driver sends a JSON POST to the configured endpoint with `to`, `message` and `code` fields and an `Authorization: Bearer <configured token>` header. Providers with other request formats require a small adapter using the `myr_send_sms` filter. Configure secrets in the destination site's administration settings; never commit them to this repository.

## Data handling and integration boundaries

National identifiers are stored as a keyed HMAC plus the last four digits. An optional student identifier can locate a parent account; it does not replace OTP verification. The site still stores learner account details and learning activity and needs a suitable access, retention and backup policy.

H5P events may supply scores, final responses and completion data. The application tracks final-response replacement counts; it does not secretly capture text deleted before submission. The privacy notice is configurable. The login template loads the Vazirmatn font from Google Fonts, so that screen is not entirely offline; adapt font delivery for restricted networks.

Public-copy changes cover documentation, clearly labeled support placeholders, a neutral preview name and landing-page wording aligned with implemented features. AI-generated teaching recommendations are not enabled. Personal fixtures, CSV rosters, local runtime, acceptance evidence and archives are deliberately excluded.

## Validation and pilot checklist

Publication checks include PHP syntax validation and JavaScript syntax checks. These do not establish end-to-end WordPress/H5P correctness or production security. This publication does not include a fresh live SMS or school-domain acceptance test.

Before deployment, verify:

- The actual WordPress/PHP versions and required H5P content libraries.
- OTP expiry, retry limits, duplicate identities and each role's access boundaries.
- Course membership, prerequisites, release dates and completion behavior for each H5P content type.
- SMS delivery with the chosen provider and consented test recipients.
- Progress reports, retention, backups, recovery and mobile accessibility.
- Site-specific support details and privacy wording.

## License notes

The original plugin metadata declares **GPLv2 or later**; that declaration is preserved in `plugin/readme.txt`. The companion theme did not specify a license in its source metadata. This publication adds no new license grant for the theme or artwork. WordPress, H5P and other dependencies retain their own licenses.

[Developer profile](https://github.com/s8700174-code) · [Project inquiries](https://ponisha.ir/profile/sabaghi_ir)
