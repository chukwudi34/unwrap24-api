resource "google_service_account" "cloud_run" {
  project      = var.project_id
  account_id   = var.cloud_run_service_account_id
  display_name = "Unwrap24 Cloud Run runtime"
  description  = "Least-privilege identity used by the Unwrap24 API on Cloud Run."

  depends_on = [google_project_service.apis]
}

# Project-level roles required by the runtime. Everything else is scoped to the
# specific bucket, repository or secret.
locals {
  cloud_run_project_roles = toset([
    "roles/cloudsql.client",
    "roles/logging.logWriter",
    "roles/monitoring.metricWriter",
  ])
}

resource "google_project_iam_member" "cloud_run" {
  for_each = local.cloud_run_project_roles

  project = var.project_id
  role    = each.value
  member  = "serviceAccount:${google_service_account.cloud_run.email}"
}

resource "google_artifact_registry_repository_iam_member" "cloud_run_reader" {
  project    = var.project_id
  location   = google_artifact_registry_repository.docker.location
  repository = google_artifact_registry_repository.docker.name
  role       = "roles/artifactregistry.reader"
  member     = "serviceAccount:${google_service_account.cloud_run.email}"
}

resource "google_storage_bucket_iam_member" "cloud_run_media" {
  bucket = google_storage_bucket.media.name
  role   = "roles/storage.objectAdmin"
  member = "serviceAccount:${google_service_account.cloud_run.email}"
}

resource "google_secret_manager_secret_iam_member" "cloud_run_accessor" {
  for_each = google_secret_manager_secret.app

  project   = var.project_id
  secret_id = each.value.secret_id
  role      = "roles/secretmanager.secretAccessor"
  member    = "serviceAccount:${google_service_account.cloud_run.email}"
}
