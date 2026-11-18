# Laravel logs to stderr (LOG_CHANNEL=stderr), which Cloud Run forwards to
# Cloud Logging. A dedicated log bucket plus a sink keeps application logs
# separate from the project _Default bucket and gives them a defined retention.
resource "google_logging_project_bucket_config" "app" {
  project        = var.project_id
  location       = "global"
  bucket_id      = "${local.name_prefix}-logs"
  retention_days = var.log_retention_days
  description    = "Application logs for the ${var.app_name} API."
}

resource "google_logging_project_sink" "app" {
  project     = var.project_id
  name        = "${local.name_prefix}-cloud-run-sink"
  description = "Routes Unwrap24 Cloud Run logs to the dedicated log bucket."

  destination = "logging.googleapis.com/projects/${var.project_id}/locations/global/buckets/${google_logging_project_bucket_config.app.bucket_id}"

  filter = join(" AND ", [
    "resource.type=\"cloud_run_revision\"",
    "resource.labels.service_name=\"${var.service_name}\"",
  ])

  unique_writer_identity = true

  depends_on = [google_project_service.apis]
}

resource "google_project_iam_member" "log_sink_writer" {
  project = var.project_id
  role    = "roles/logging.bucketWriter"
  member  = google_logging_project_sink.app.writer_identity

  condition {
    title       = "unwrap24-log-bucket-only"
    description = "Restrict the sink writer identity to the Unwrap24 log bucket."
    expression  = "resource.name == \"projects/${var.project_id}/locations/global/buckets/${google_logging_project_bucket_config.app.bucket_id}\""
  }
}
