# Unwrap24 API — Google Cloud Infrastructure

Terraform configuration that provisions the production Google Cloud footprint
for the Unwrap24 Laravel API, plus the container packaging it runs in.

> **Status:** this infrastructure has been **validated locally**
> (`terraform fmt`, `terraform init -backend=false`, `terraform validate`) but
> has **not** been applied against a real Google Cloud project. No GCP resources
> were created or verified while building this configuration.

## Architecture

```
                          ┌──────────────────────────────┐
   Next.js frontend ─────▶ │  Cloud Run: unwrap24-api     │
   (HTTPS, bearer token)   │  PHP 8.3 + nginx + FPM       │
                          │  dedicated service account   │
                          └───────┬───────────┬──────────┘
                                  │           │
              Cloud SQL Unix      │           │  Secret Manager refs
              socket (/cloudsql)  │           │  (APP_KEY, DB password,
                                  ▼           │   OAuth/Stripe secrets)
                    ┌──────────────────┐      │
                    │ Cloud SQL MySQL  │      ▼
                    │ 8.0 (private to  │  ┌──────────────────────┐
                    │ connector, TLS)  │  │ Secret Manager       │
                    └──────────────────┘  └──────────────────────┘
                                  │
                                  │  Application Default Credentials
                                  ▼
                    ┌──────────────────────┐      ┌───────────────────────┐
                    │ Cloud Storage bucket │      │ Cloud Logging         │
                    │ calendar media (gcs) │      │ stderr → log bucket   │
                    └──────────────────────┘      └───────────────────────┘

  Images: Artifact Registry ──▶ Cloud Run revision
```

The application itself is unchanged in behaviour: local development still uses
the `local` filesystem disk and a local MySQL/SQLite connection. Cloud Run
injects `FILESYSTEM_DISK=gcs` and the Cloud SQL Unix socket path at runtime.

## Infrastructure components

| Component | Terraform resource | Notes |
|---|---|---|
| Required APIs | `google_project_service.apis` | 13 APIs, `disable_on_destroy = false` |
| Docker repository | `google_artifact_registry_repository.docker` | Cleanup: keep last 10 versions, delete untagged > 14 days |
| Cloud Run service | `google_cloud_run_v2_service.api` | Port 8080, `/up` startup + liveness probes, scale 0→10, concurrency 80 |
| Cloud SQL | `google_sql_database_instance.main`, `google_sql_database.app`, `google_sql_user.app` | MySQL 8.0, TLS required, no authorized networks, automated backups |
| Cloud Storage | `google_storage_bucket.media` | Uniform access, public access prevention enforced, versioning, CORS for the frontend |
| Secrets | `google_secret_manager_secret.app`, `google_secret_manager_secret_version.app` | `app-key`, `db-password`, `google-client-secret`, `stripe-secret-key`, `stripe-webhook-secret` |
| Runtime identity | `google_service_account.cloud_run` | Dedicated, no keys |
| Least-privilege IAM | `google_project_iam_member.cloud_run`, repository/bucket/secret-scoped bindings | See table below |
| Public access | `google_cloud_run_v2_service_iam_member.public_invoker` | `roles/run.invoker` for `allUsers` when `allow_unauthenticated = true` |
| Cloud Logging | `google_logging_project_bucket_config.app`, `google_logging_project_sink.app` | Dedicated bucket, retention from `log_retention_days` |

### IAM granted to the Cloud Run service account

| Scope | Role | Why |
|---|---|---|
| Project | `roles/cloudsql.client` | Cloud SQL connector |
| Project | `roles/logging.logWriter` | Ship stdout/stderr to Cloud Logging |
| Project | `roles/monitoring.metricWriter` | Cloud Run/Cloud SQL metrics |
| Artifact Registry repository | `roles/artifactregistry.reader` | Pull the API image |
| Media bucket | `roles/storage.objectAdmin` | Read/write/delete calendar media |
| Each secret | `roles/secretmanager.secretAccessor` | Read only the secrets the app needs |

No service account keys are created. Cloud Run authenticates with the
service account identity via the metadata server (Application Default
Credentials).

## Required APIs

Enabled by `apis.tf`:

```
artifactregistry.googleapis.com   cloudresourcemanager.googleapis.com
cloudsql.googleapis.com           iam.googleapis.com
logging.googleapis.com            monitoring.googleapis.com
run.googleapis.com                secretmanager.googleapis.com
serviceusage.googleapis.com       sql-component.googleapis.com
storage-api.googleapis.com        storage-component.googleapis.com
storage.googleapis.com
```

The identity running Terraform additionally needs permission to enable
services and create the resources above (for example `roles/owner` on a
dedicated infrastructure project, or a narrower custom role covering
`serviceusage.services.enable`, Cloud Run, Cloud SQL, Artifact Registry,
Secret Manager, Storage and Logging admin permissions).

## Terraform initialization

```bash
cd terraform
cp terraform.tfvars.example terraform.tfvars   # git-ignored; fill in values
terraform init
```

For local validation without any backend or credentials:

```bash
terraform init -backend=false
```

### Remote state (recommended for real deployments)

State is **not** configured in `versions.tf` so validation works offline. Before
a real deployment, create a state bucket and uncomment the `backend "gcs"` block
(see `versions.tf`), then re-run `terraform init`. Never commit `*.tfstate`,
`*.tfvars` or `.terraform/` — those are covered by `.gitignore` in the repo root
and in this directory.

## Terraform validation

```bash
terraform fmt -recursive
terraform fmt -check -recursive
terraform init -backend=false
terraform validate
```

Expected output for `validate`:

```
Success! The configuration is valid.
```

## Deployment instructions

Terraform creates the Cloud Run service pointing at an image tag, so the image
must exist before the first full apply. Deploy in two phases.

1. **Bootstrap infrastructure and the image repository**

   ```bash
   cd terraform
   terraform init
   terraform apply -target=google_project_service.apis \
                   -target=google_artifact_registry_repository.docker
   ```

2. **Build and push the image**

   ```bash
   # from the repository root
   gcloud auth configure-docker us-central1-docker.pkg.dev
   IMAGE="us-central1-docker.pkg.dev/<PROJECT_ID>/unwrap24/unwrap24-api:$(git rev-parse --short HEAD)"
   docker build -t "$IMAGE" .
   docker push "$IMAGE"
   ```

3. **Apply the rest, deploying that tag**

   ```bash
   cd terraform
   terraform apply -var="image_tag=$(git rev-parse --short HEAD)"
   ```

4. **Run migrations** (Cloud SQL is only reachable through the connector):
   use the Cloud SQL Auth Proxy locally, or a one-off Cloud Run job built from
   the same image:

   ```bash
   gcloud run jobs create unwrap24-migrate \
     --image "$IMAGE" \
     --region us-central1 \
     --set-secrets "APP_KEY=unwrap24-production-app-key:latest,DB_PASSWORD=unwrap24-production-db-password:latest" \
     --set-env-vars "DB_CONNECTION=mysql,DB_HOST=127.0.0.1,DB_NAME=unwrap24,DB_USER=unwrap24" \
     --set-cloudsql-instances "<PROJECT_ID>:us-central1:unwrap24-mysql" \
     --command "php" --args "artisan,migrate,--force"
   gcloud run jobs execute unwrap24-migrate --region us-central1 --wait
   ```

5. **Configure Google OAuth** — add
   `https://<service-url>/api/auth/google/callback` as an authorized redirect
   URI, then set `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` in `terraform.tfvars`
   and re-apply (the secret gets a new version and Cloud Run picks it up on the
   next revision).

### Updating the application

```bash
docker build -t "$IMAGE" . && docker push "$IMAGE"
cd terraform && terraform apply -var="image_tag=<new-tag>"
```

## Infrastructure destruction

```bash
cd terraform
terraform destroy
```

Notes:

- `db_deletion_protection = true` by default: set it to `false` before
  destroying, otherwise the Cloud SQL instance is protected.
- Cloud Storage `force_destroy = false` by default: empty the media bucket or
  set `force_destroy = true` first.
- APIs are not disabled on destroy (`disable_on_destroy = false`).
- Cloud Run has `deletion_protection = false` so destroy works unattended.

## Estimated cost considerations

| Resource | Default configuration | Cost driver |
|---|---|---|
| Cloud Run | 0→10 instances, 1 vCPU / 512 MiB, concurrency 80 | Per request/instance-second; scale-to-zero keeps idle cost near zero |
| Cloud SQL MySQL | `db-f1-micro`, 20 GB SSD, ZONAL, backups on | Always-on instance + storage + backups — usually the largest fixed cost |
| Cloud Storage | Standard, versioning on | GB stored + operations; versioning and media retention add storage |
| Artifact Registry | Docker, latest 10 versions kept | GB stored beyond the free tier |
| Secret Manager | 5 secrets, few versions | Per active secret version + access operations |
| Cloud Logging | Dedicated bucket, 30-day retention | Log ingestion/storage beyond free allotment |
| Egress | Cloud Run → internet | External egress to the frontend/browser |

For a production launch, consider raising `db_tier`, switching
`db_availability_type` to `REGIONAL`, and enabling point-in-time recovery — each
increases cost.

## Security notes

- Production secrets are Secret Manager values referenced by Cloud Run; none are
  written into the image, the Dockerfile or Terraform variables files committed
  to git.
- `APP_KEY` and the database password are generated with `random_password` when
  not supplied, so no default credentials exist in the repository.
- The media bucket enforces uniform bucket-level access and public access
  prevention; objects are served through signed/temporary URLs.
- Cloud SQL requires TLS and has no authorized networks; only the Cloud SQL
  connector from Cloud Run can reach it.
- Local `.env`, `terraform.tfvars`, Terraform state and service account key
  files are git-ignored.
