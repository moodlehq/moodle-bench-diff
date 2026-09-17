# AGENTS.md

Guidance for AI coding agents working on this repository.

## What this app does

A Symfony 6.4 (PHP 8.1+) web app + CLI that compares Moodle performance test
run "datasets" (JMeter-style benchmark JSON exports) and reports whether a
run has regressed vs. a baseline. All app code lives under `application/`
(this is a Symfony project root — always `cd application` before running
`composer`/`bin/console`/`symfony` commands).

## Architecture

- **`src/Model/`** — plain data objects: `Dataset` (a full benchmark run,
  containing many `Scenario`s), `Scenario` (one test step, with raw
  per-thread `data` rows), `Result`/`ComparisonResult` (outcome of comparing
  two scenarios' metrics). `Dataset` is deliberately **lazy**: it can be
  constructed from a lightweight S3 cache summary (`Dataset::fromCache`,
  no `data.results`) and only loads full per-scenario data on first call to
  `getScenarios()`/`getScenario()` via `_loadFullDataset()`, which calls back
  into the loader that created it (`_loader->loadFullDataset($name, true)`).
  Don't call `->scenarios` directly on a cached-only Dataset without going
  through `getScenarios()`.
- **`src/Service/DatasetLoaderInterface`** — abstraction for fetching a
  dataset by name (`datasetExists`, `loadFullDataset`). Two implementations:
  - `FilebasedDatasetLoader` — reads `<app.datasets_path>/<name>.json` from
    local disk (used e.g. by CLI comparisons of local files).
  - `S3DatasetLoader` — reads from S3 (bucket/path from
    `app.s3_bucket`/`app.s3_dataset_path` params), implements
    `CachingDatasetLoaderInterface`, and adds a `listDatasets()` method (using
    a `Symfony\Component\Cache\Adapter\FilesystemAdapter` keyed by
    `sha1($datasetName)`) that is **not** part of `DatasetLoaderInterface`.
    `services.yaml` binds `DatasetLoaderInterface` to `S3DatasetLoader`, so
    code type-hinted against the interface (e.g. `IndexController`,
    `MoodleWarmCacheCommand`) still calls `->listDatasets()` — this only
    works because DI wires the concrete S3 class; if you ever swap in
    `FilebasedDatasetLoader` for those services, `listDatasets()` will break.
- **`src/Service/DatasetComparator`** (implements `DatasetComparatorInterface`)
  — the core diffing logic. For each scenario/metric pair it compares both
  the **total** and the **average** between a "before" and "after" dataset,
  using a direction map (`LOWER_IS_BETTER`/`HIGHER_IS_BETTER`) and per-metric
  thresholds (`SCENARIO_THRESHOLDS`, `TOTAL_THRESHOLDS`) to decide
  success/failure. Some metrics (`dbquerytime`, `timeused`, `time`,
  `latency`, `bytes`) are always ignored via `isKeyIgnored()` even if they
  regress — these are considered too noisy to gate on.
  There is a third map, `PERCENTAGE_THRESHOLDS`, holding a growth budget as a
  percentage of the before value; the effective allowance is the larger of it
  and the absolute threshold, so the absolute entry acts as a floor for small
  or zero baselines. Keep the two kinds of allowance distinct when tuning:
  the absolute maps absorb run-to-run *noise* (`dbwrites` and session
  `lastaccess` timing), while a percentage budget permits deliberate
  structural *growth* in a metric that is otherwise deterministic — currently
  only `filesincluded`, which grows as monolithic `lib.php` files are split
  into autoloaded classes. Note a total is the sum over `PERF_LOOPS`, so an
  absolute threshold gates a total five times more tightly than the average
  it derives from (which is why such failures are reported twice); a
  percentage applies identically to both.
  ⚠️ `src/Service/Comparisons.php` is an **older duplicate** of this same
  threshold/key logic (used only by `IndexController` for chart metric keys)
  — don't "fix" one without checking if the same bug exists in the other;
  prefer consolidating onto `DatasetComparator` if editing this area.
- **`src/Service/DatasetFilter`** — filters a list of `Dataset` by matching
  getter methods dynamically (`'get' . ucfirst($filterName)`), used to drive
  the web UI's filter form.
- **Controllers**: `IndexController` (dataset list, filter form, comparison
  picker, Chart.js bar charts via `symfony/ux-chartjs`) and
  `ComparisonController` (renders `/compare/{before}/{after}` — note this
  route currently just passes raw names to the twig template, it does not
  call `DatasetComparator` itself).
- **Commands** (`src/Command/`): `moodle:compare-results <before> <after>`
  (CLI equivalent of the comparison, prints a table, exits `Command::FAILURE`
  if any metric fails — use this for exit-code-based CI gating) and
  `moodle:warm-cache` (`#[AsPeriodicTask(frequency: '1 hour')]` via
  `symfony/scheduler` — pre-warms the S3 dataset-list filesystem cache).

## Conventions specific to this codebase

- Private/internal methods are prefixed with a leading underscore
  (`_loadFullDataset`, `_addResults`, `_getCacheKeyForDataset`) — follow this
  when adding private helpers to `Service`/`Model` classes.
- Trailing commas after the last constructor/method parameter are standard
  (PHP 8.1 style used throughout), as is one-parameter-per-line for
  constructor promotion.
- Dataset JSON shape: top-level object with `host`, `sitepath`, `group`,
  `rundesc`, `users`, `loopcount`, `rampup`, `throughput`, `size`,
  `baseversion`, `siteversion`, `sitebranch`, `sitecommit`, `runTime`, and
  `results` = array of "threads", each an array of result rows with at least
  a `name` (scenario) plus numeric metric keys (`dbreads`, `dbwrites`,
  `dbquerytime`, `memoryused`, `filesincluded`, `serverload`, `sessionsize`,
  `timeused`, `bytes`, `time`, `latency`).
- Config secrets (AWS keys, S3 bucket) are Symfony encrypted secrets under
  `config/secrets/prod/` and injected as `%env(AWS_...)%` — never hardcode
  credentials; there are two aws config blocks (`config/config.yml` legacy
  vs `config/packages/aws.yaml` vs `services.yaml`) with inconsistent env var
  names (`AWS_KEY`/`AWS_SECRET` vs `AWS_ACCESS_KEY`/`AWS_ACCESS_SECRET_KEY`)
  — check which one is actually loaded (`config/packages/aws.yaml` +
  `services.yaml`'s `aws:` block) before assuming an env var name is correct.

## Dev workflows

- Install deps: `cd application && composer install`.
- Run locally: `symfony server:start` (or `symfony serve`) from `application/`;
  Docker image (`Dockerfile`) runs the same via
  `symfony server:start --port=80 --no-tls --allow-http`.
- Run the CLI comparator: `php bin/console moodle:compare-results before.json after.json [-v]`.
  Names are resolved as S3 keys, because `services.yaml` binds
  `DatasetLoaderInterface` to `S3DatasetLoader` — without AWS config the
  command cannot run at all. Pass `--local` to treat the two arguments as
  paths to result files on disk instead (uses `LocalPathDatasetLoader`, needs
  no AWS credentials); this is the way to compare two runs you have locally.
- Run the tests: `cd application && composer install && vendor/bin/phpunit`
  (PHPUnit 11, config in `phpunit.dist.xml`). Coverage is limited to
  `DatasetComparator` and `LocalPathDatasetLoader`; the web UI and the S3
  loader are still only verifiable by hand. **If you touch the comparison
  direction, the thresholds or the ignored-key list, the comparator tests are
  the safety net — check they still fail when the behaviour is wrong, not just
  that they pass.** Note `Scenario::getAverage()` divides by the number of
  samples carrying a key, so fixtures must populate every metric key.
- Front-end assets are managed via Symfony AssetMapper + importmap (see
  `importmap.php`, `assets/`), not webpack/npm — add JS via
  `assets/controllers/*.js` (Stimulus controllers, e.g. `hello_controller.js`)
  and register in `assets/controllers.json`, not a bundler config.

