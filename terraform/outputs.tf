output "service_url" {
  description = "Public URL of the Cloud Run service."
  value       = google_cloud_run_v2_service.api.uri
}

output "service_name" {
  description = "Cloud Run service name."
  value       = google_cloud_run_v2_service.api.name
}

output "artifact_registry_repository" {
  description = "Artifact Registry repository path used for docker push."
  value       = "${var.region}-docker.pkg.dev/${var.project_id}/${google_artifact_registry_repository.docker.repository_id}"
}

output "container_image" {
  description = "Fully qualified image reference deployed by Terraform."
  value       = local.image
}

output "cloud_sql_connection_name" {
  description = "Cloud SQL instance connection name (project:region:instance)."
  value       = local.sql_connection_name
}

output "cloud_sql_instance_name" {
  description = "Cloud SQL instance name."
  value       = google_sql_database_instance.main.name
}

output "media_bucket" {
  description = "Cloud Storage bucket that holds calendar media."
  value       = google_storage_bucket.media.name
}

output "cloud_run_service_account" {
  description = "Runtime service account email."
  value       = google_service_account.cloud_run.email
}

output "secret_ids" {
  description = "Secret Manager secret IDs wired into Cloud Run."
  value       = { for key, secret in google_secret_manager_secret.app : key => secret.secret_id }
}

output "log_bucket_id" {
  description = "Cloud Logging bucket receiving Cloud Run logs."
  value       = google_logging_project_bucket_config.app.bucket_id
}
