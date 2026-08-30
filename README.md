# Horus

Horus is a lightweight PHP HTTP ESB used to route, transform, validate, enrich, and fan out financial messages over HTTP. It is mainly configured through JSON files and template assets, making it suitable for integration labs, simulators, payment-message gateways, and ISO 20022/SWIFT-style message processing chains.

The project provides several HTTP entrypoints, each represented by a colored processing role in the code and tracing output:

| Role | Entrypoint | Purpose |
| --- | --- | --- |
| Green / Yellow | `src/HorusNew.php` | Main transformation engine for XML, JSON, and template injection requests. Yellow is used when `type=inject`. |
| Orange | `src/horusRouterNew.php` | HTTP router/fan-out component driven by `conf/horusRouting.json`. |
| Indigo | `src/horusRecurse.php` | Recursive XML/JSON decomposition and recomposition engine driven by `conf/horusRecurse.json`. |
| White | `src/horusRoadmap.php` | Roadmap-based orchestration engine driven by roadmap configuration. |

## Table of contents

- [Main capabilities](#main-capabilities)
- [Repository layout](#repository-layout)
- [Requirements](#requirements)
- [Installation](#installation)
- [Running Horus](#running-horus)
- [HTTP API](#http-api)
- [Configuration guide](#configuration-guide)
- [Templates, schemas, filters, and mappers](#templates-schemas-filters-and-mappers)
- [Examples](#examples)
- [Observability and logging](#observability-and-logging)
- [Testing](#testing)
- [Docker](#docker)
- [Security notes](#security-notes)
- [Development notes](#development-notes)
- [License](#license)

## Main capabilities

- **HTTP routing and fan-out**: route an incoming request to one or more destinations, optionally through proxy/transformation steps.
- **XML transformation**: identify input XML using XSD validation, extract variables using XPath, render response templates, and validate generated XML against output XSD files.
- **JSON transformation**: match JSON payloads using key/value rules or JSONPath expressions and render configured JSON responses.
- **Template injection**: generate configured XML, JSON, or text templates from user-provided parameters.
- **Recursive message processing**: split structured messages into parts, transform each part, and rebuild the result.
- **Roadmap orchestration**: select an orchestration roadmap based on source, headers, query parameters, and input content.
- **Multipart support**: process multipart request bodies in the main transformation and routing flows.
- **Signature utilities**: validate or generate XML signature structures used by SWIFT/DataPDU-style messages.
- **Tracing**: create OpenTelemetry/Zipkin-compatible spans for the main processing roles.

## Repository layout

```text
conf/          Runtime configuration files
filters/       Optional filter implementations
lib/           Core PHP classes for HTTP, business routing, XML, JSON, recursion, tracing, signatures
mappers/       Mapper interfaces and sample mappers
samples/       Example payloads and sample configuration fragments
src/           HTTP entrypoints, helper tools, and browser injector UI assets
templates/     Response and generated-message templates
tests/         PHPUnit test suite
transforms/    Transformation scripts
ui/            UI assets
xsd/           XML schemas used for input detection and output validation
Dockerfile     Apache/PHP image definition
composer.json  PHP package metadata and dependencies
```

## Requirements

- PHP 8.4, as declared in `composer.json` and used by the Docker image.
- Composer.
- PHP extensions used by the project/runtime image: `soap`, `bcmath`, `sockets`, `rdkafka` when Kafka consumer integration is required, and XML/DOM/libxml support.
- Optional observability backend compatible with Zipkin/OpenTelemetry, for example Jaeger exposing a Zipkin collector endpoint.

## Installation

From the repository root:

```bash
composer install
```

For local development with PHP's built-in server, expose the `src` directory. The entrypoint files use relative paths such as `../lib` and `../conf`, so running from `src` works well for quick tests:

```bash
cd src
php -S 127.0.0.1:8080
```

Then call entrypoints such as `http://127.0.0.1:8080/HorusNew.php`.

## Running Horus

### Main transformation engine

`src/HorusNew.php` chooses one of three modes:

1. **Injector mode** when `?type=inject` is present.
2. **Simple JSON mode** when `?type=simplejson` and the request content type starts with `application/json`.
3. **XML mode** for all other requests.

It returns HTTP 200 for both successful and business-error responses, with the error body generated from configured templates. Fatal configuration decoding errors return HTTP 500.

### Router engine

`src/horusRouterNew.php` reads `conf/horusRouting.json`, selects a route using the `source` query parameter, and posts the request body to every configured destination. It returns JSON with either `result: OK` and downstream `responses`, or `result: KO` and a failure `message`.

### Recursive engine

`src/horusRecurse.php` reads `conf/horusRecurse.json`, applies the matching recursive section, and optionally forwards output to the URL provided in the destination header.

### Roadmap engine

`src/horusRoadmap.php` selects and applies a configured roadmap using the `source` query parameter, request headers, query parameters, and request body.

## HTTP API

### Common headers

| Header | Purpose |
| --- | --- |
| `X-Business-Id` | Correlation/business identifier. If omitted, Horus generates one. |
| `x_destination_url` | Optional forwarding URL used by transformation components. The project constant also refers to this as the destination header. |
| `Accept` | Preferred response content type where supported. |
| `Content-Type` | Input content type. XML, JSON, and multipart payloads are supported depending on the entrypoint. |

### `POST /HorusNew.php`

Main XML transformation endpoint.

```bash
curl -X POST "http://127.0.0.1:8080/HorusNew.php" \
  -H "Content-Type: application/xml" \
  -H "Accept: application/xml" \
  --data-binary @samples/pacs008.xml
```

### `POST /HorusNew.php?type=simplejson`

JSON matching and response generation endpoint.

```bash
curl -X POST "http://127.0.0.1:8080/HorusNew.php?type=simplejson" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"node":[{"value":"one"},{"value":"two"}],"dummy":"dummyvalue"}'
```

### `POST /HorusNew.php?type=inject`

Template injection endpoint. Available templates and parameters are defined in `conf/injectorParams.json` and can be listed with `getHorusDestinations.php`.

```bash
curl -X POST "http://127.0.0.1:8080/HorusNew.php?type=inject" \
  -H "Content-Type: application/json" \
  -d '{"template":"pacs.002_ACCP.xml","params":{"id":"ABC123"}}'
```

### `POST /horusRouterNew.php?source=<source>`

Route a request according to `conf/horusRouting.json`.

```bash
curl -X POST "http://127.0.0.1:8080/horusRouterNew.php?source=singlesource" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"test":"ok"}'
```

### `POST /horusRecurse.php`

Apply recursive transformation rules.

```bash
curl -X POST "http://127.0.0.1:8080/horusRecurse.php" \
  -H "Content-Type: application/xml" \
  --data-binary @samples/cristal_full.xml
```

### `POST /horusRoadmap.php?source=<source>`

Apply roadmap orchestration.

```bash
curl -X POST "http://127.0.0.1:8080/horusRoadmap.php?source=my-source" \
  -H "Content-Type: application/xml" \
  --data-binary @samples/sample.xml
```

### `GET /getHorusDestinations.php`

Returns the available injector templates and their required parameters from `conf/injectorParams.json`.

```bash
curl "http://127.0.0.1:8080/getHorusDestinations.php"
```

## Configuration guide

### Global configuration: `conf/horusConfig.json`

| Key | Description |
| --- | --- |
| `tracerCollectorHost` | Legacy/collector host for tracing. |
| `zipkinUrl` | Zipkin-compatible endpoint used by tracing. |
| `broker.list` | Kafka broker list used by consumer integrations. |
| `rfh2Prefix`, `mqmdPrefix` | Header prefixes for MQ-style metadata. |
| `logLocation` | PHP stream or path where Horus writes logs. Defaults to standard output in Docker. |

### Transformation configuration: `conf/horusParams.json`

This file drives `HorusNew.php` XML and simple JSON modes.

Top-level keys include:

| Key | Description |
| --- | --- |
| `errorTemplate` | Default template used for generated error responses. |
| `errorFormat` | MIME type for generated errors. |
| `pacsDefaultOutputContentType` | Default response content type for XML/PACS flows. |
| `simplejson` | JSON matching and response-generation rules. |
| `pacs` | XML/XSD matching, XPath extraction, template, forwarding, and validation rules. |

An XML rule typically contains:

```json
{
  "query": "RTGS_pacs.008.001.08.xsd",
  "queryMatch": "AAAAAAA",
  "comment": "Pacs8 to Pacs2",
  "responseFormat": "RTGS_pacs.002.001.10.xsd",
  "responseTemplate": "pacs2from8.xml",
  "errorTemplate": "errorTemplate.xml",
  "parameters": {
    "msgid": "/u:Document/u:FIToFICstmrCdtTrf/u:GrpHdr/u:MsgId"
  }
}
```

Rule behavior:

- `query` is usually the detected input schema name.
- `queryMatch` is an optional regular expression matched against input content. It may contain `${queryParam}` placeholders that are substituted from URL query parameters.
- `selector` can further restrict a rule to a query parameter key/value pair.
- `responseTemplate` names a file under `templates/`.
- `responseFormat` names an output XSD under `xsd/`; use `novalidation` to skip output validation or `null` for an intentionally empty response.
- `parameters` maps variable names to XPath expressions. Templates can use these variables when rendering output.
- `destParameters` can append forwarding/query/header parameters, including computed `phpvalue` snippets.

### Router configuration: `conf/horusRouting.json`

`horusRouterNew.php` reads `RoutingTable` and selects the object where `source` equals the `source` query parameter.

Important fields:

- `parameters`: shared parameters merged into downstream query strings.
- `destinations`: one or more HTTP calls to execute.
- `proxy`: optional transformation/proxy endpoint called before `destination`.
- `destination`: final HTTP endpoint.
- `proxyParameters`: parameters for the proxy step.
- `destParameters`: parameters for the destination step.
- `delayafter`: delay, in seconds, after the destination call.
- `followOnError`: when set to `false`, can be used to control continuation behavior after downstream failures.

### Injector configuration: `conf/injectorParams.json`

This file exposes the templates that can be generated by the injector UI/API. Each key is a template file name under `templates/`. `type` is the generated content type and `params` lists supported variables.

### Recursive configuration: `conf/horusRecurse.json`

`horusRecurse.php` uses this file to split a document into sections and parts, optionally transform each part via another Horus endpoint, then reassemble the message.

Typical fields include `section`, `content-type`, `schema`, `namespaces`, `rootElement`, `parts`, `variables`, `transformUrl`, `targetPath`, `targetElement`, `targetElementOrder`, `validator`, and `signature`.

## Templates, schemas, filters, and mappers

### Templates

Templates live in `templates/` and are included by PHP during rendering. They can use variables extracted from input messages or query parameters. Common template families include ISO 20022 messages such as `pacs.*`, `camt.*`, `admi.*`, and `head.*`, SWIFT/DataPDU wrappers, JSON response templates, and generic error templates.

### Schemas

Schemas live in `xsd/`. Horus uses them to detect incoming XML document types by namespace/schema validation and to validate generated XML responses unless `responseFormat` is set to `novalidation` or `null`.

### Filters

Filters live in `filters/` and implement `filters/horusFilterInterface.php`. They can validate or alter data in custom flows.

### Mappers

Mappers live in `mappers/` and implement mapper interfaces for dynamic/custom transformations.

## Examples

### Transform an XML sample

```bash
cd src
php -S 127.0.0.1:8080
```

In another terminal:

```bash
curl -X POST "http://127.0.0.1:8080/HorusNew.php" \
  -H "Content-Type: application/xml" \
  -H "Accept: application/xml" \
  --data-binary @../samples/pacs008.xml
```

### Route a JSON payload

```bash
curl -X POST "http://127.0.0.1:8080/horusRouterNew.php?source=singlesource" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Business-Id: demo-001" \
  -d '{"test":"ok"}'
```

### List injectable templates

```bash
curl "http://127.0.0.1:8080/getHorusDestinations.php" | jq
```

### Check XML signatures from the CLI

```bash
php src/check_signature.php BHA samples/xmldsig.xml
php src/check_signature.php LAU samples/xmldsig.xml secret
```

## Observability and logging

Horus creates tracing spans through `lib/horus_tracing.php`. Global tracing configuration is read from `conf/horusConfig.json`, especially `zipkinUrl`.

Processing colors are used as service/flow labels:

- `GREEN`: main XML transformation
- `YELLOW`: injector mode
- `ORANGE`: router
- `INDIGO`: recursive processor
- `WHITE`: roadmap processor

Logs are written to the location configured by `logLocation`, typically `php://stdout` in containers.

## Testing

Run the PHPUnit suite from the repository root:

```bash
./vendor/bin/phpunit tests
```

The tests cover business matching, routing, HTTP helpers, XML processing, recursion, signatures, injection, and simple JSON behavior.

## Docker

Build the image:

```bash
docker build -t horus .
```

Run it locally:

```bash
docker run --rm -p 8080:80 horus
```

Then call endpoints under `http://127.0.0.1:8080/`.

The provided `Dockerfile` uses `php:8.4-apache`, installs required PHP extensions, enables Apache rewrite support, and copies the application, configuration, templates, schemas, UI, and vendor dependencies into `/var/www/html`.

## Security notes

- Configuration files may contain endpoint URLs, broker addresses, keys, certificates, or sample secrets. Replace sample values before production use.
- Some configuration fields, such as `phpvalue`, execute PHP snippets while building parameters. Treat configuration as trusted code and restrict write access.
- XML signature material in sample configuration is for development/testing only unless explicitly replaced with secure production keys.
- Validate all schemas/templates and limit externally supplied forwarding URLs before exposing Horus beyond trusted environments.
- Review logging before processing sensitive payment data, because payloads and headers can be logged at `INFO` or `DEBUG` level.

## Development notes

- Keep entrypoint-relative paths in mind. Many files are referenced as `../conf`, `../templates`, and `../xsd` from `src/`.
- Add new XML flows by placing an XSD in `xsd/`, a response template in `templates/`, and a rule in `conf/horusParams.json`.
- Add new router flows by extending `conf/horusRouting.json` with a new `source` route.
- Add new injector templates by creating the template under `templates/` and registering it in `conf/injectorParams.json`.
- Add or update automated coverage in `tests/` when changing routing, matching, signature, or transformation behavior.

## License

Horus is licensed under the Apache License 2.0. See [LICENSE](LICENSE).