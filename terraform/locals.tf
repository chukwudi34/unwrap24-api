locals {
  name_prefix = "${var.app_name}-${var.environment}"

  labels = merge(
    {
      application = var.app_name
      environment = var.environment
      managed_by  = "terraform"
    },
    var.labels,
  )

  bucket_name = var.media_bucket_name != "" ? var.media_bucket_name : "${var.project_id}-${local.name_prefix}-media"

  # Cloud Run's stable URL format includes the project number.
  cloud_run_url = var.app_url != "" ? var.app_url : "https://${var.service_name}-${data.google_project.current.number}.${var.region}.run.app"

  image = "${var.region}-docker.pkg.dev/${var.project_id}/${google_artifact_registry_repository.docker.repository_id}/${var.service_name}:${var.image_tag}"

  sql_connection_name = google_sql_database_instance.main.connection_name
}
