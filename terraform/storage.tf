resource "google_storage_bucket" "media" {
  project                     = var.project_id
  name                        = local.bucket_name
  location                    = var.region
  storage_class               = "STANDARD"
  uniform_bucket_level_access = true
  public_access_prevention    = "enforced"
  force_destroy               = var.bucket_force_destroy
  labels                      = local.labels

  versioning {
    enabled = true
  }

  dynamic "lifecycle_rule" {
    for_each = var.media_retention_days > 0 ? [1] : []

    content {
      condition {
        age        = var.media_retention_days
        with_state = "ARCHIVED"
      }

      action {
        type = "Delete"
      }
    }
  }

  cors {
    origin          = [var.frontend_url]
    method          = ["GET", "HEAD", "PUT", "POST"]
    response_header = ["Content-Type", "Content-MD5", "Authorization"]
    max_age_seconds = 3600
  }

  depends_on = [google_project_service.apis]
}
