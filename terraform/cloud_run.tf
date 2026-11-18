resource "google_cloud_run_v2_service" "api" {
  project             = var.project_id
  name                = var.service_name
  location            = var.region
  ingress             = "INGRESS_TRAFFIC_ALL"
  labels              = local.labels
  deletion_protection = false

  template {
    service_account = google_service_account.cloud_run.email
    timeout         = "60s"

    max_instance_request_concurrency = var.container_concurrency

    scaling {
      min_instance_count = var.min_instance_count
      max_instance_count = var.max_instance_count
    }

    containers {
      name  = "api"
      image = local.image

      ports {
        container_port = 8080
      }

      resources {
        limits = {
          cpu    = var.container_cpu
          memory = var.container_memory
        }
        cpu_idle          = true
        startup_cpu_boost = true
      }

      # --- Laravel runtime ---------------------------------------------------
      env {
        name  = "APP_NAME"
        value = "Unwrap24"
      }
      env {
        name  = "APP_ENV"
        value = "production"
      }
      env {
        name  = "APP_DEBUG"
        value = "false"
      }
      env {
        name  = "APP_URL"
        value = local.cloud_run_url
      }
      env {
        name = "APP_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app["app-key"].secret_id
            version = "latest"
          }
        }
      }
      env {
        name  = "LOG_CHANNEL"
        value = "stderr"
      }
      env {
        name  = "LOG_LEVEL"
        value = var.log_level
      }
      env {
        name  = "LOG_STACK"
        value = "stderr"
      }

      # --- Cloud SQL (Unix socket mounted from the Cloud SQL connector) ------
      env {
        name  = "DB_CONNECTION"
        value = "mysql"
      }
      env {
        name  = "DB_HOST"
        value = "127.0.0.1"
      }
      env {
        name  = "DB_PORT"
        value = "3306"
      }
      env {
        name  = "DB_DATABASE"
        value = google_sql_database.app.name
      }
      env {
        name  = "DB_USERNAME"
        value = google_sql_user.app.name
      }
      env {
        name  = "DB_SOCKET"
        value = "/cloudsql/${local.sql_connection_name}"
      }
      env {
        name = "DB_PASSWORD"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app["db-password"].secret_id
            version = "latest"
          }
        }
      }

      # --- Cloud Storage -----------------------------------------------------
      env {
        name  = "FILESYSTEM_DISK"
        value = "gcs"
      }
      env {
        name  = "GOOGLE_CLOUD_PROJECT_ID"
        value = var.project_id
      }
      env {
        name  = "GOOGLE_CLOUD_STORAGE_BUCKET"
        value = google_storage_bucket.media.name
      }
      env {
        name  = "GOOGLE_CLOUD_STORAGE_PATH_PREFIX"
        value = "calendar-media"
      }

      # --- Application services ---------------------------------------------
      env {
        name  = "SESSION_DRIVER"
        value = "database"
      }
      env {
        name  = "CACHE_STORE"
        value = "database"
      }
      env {
        name  = "QUEUE_CONNECTION"
        value = "database"
      }
      env {
        name  = "FRONTEND_URL"
        value = var.frontend_url
      }
      env {
        name  = "MAIL_MAILER"
        value = var.mail_mailer
      }
      env {
        name  = "MAIL_FROM_ADDRESS"
        value = var.mail_from_address
      }
      env {
        name  = "MAIL_FROM_NAME"
        value = "Unwrap24"
      }

      # --- OAuth / billing ---------------------------------------------------
      env {
        name  = "GOOGLE_CLIENT_ID"
        value = var.google_client_id
      }
      env {
        name  = "GOOGLE_REDIRECT_URI"
        value = "${local.cloud_run_url}/api/auth/google/callback"
      }
      env {
        name = "GOOGLE_CLIENT_SECRET"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app["google-client-secret"].secret_id
            version = "latest"
          }
        }
      }
      env {
        name  = "STRIPE_PUBLISHABLE_KEY"
        value = var.stripe_publishable_key
      }
      env {
        name = "STRIPE_SECRET_KEY"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app["stripe-secret-key"].secret_id
            version = "latest"
          }
        }
      }
      env {
        name = "STRIPE_WEBHOOK_SECRET"
        value_source {
          secret_key_ref {
            secret  = google_secret_manager_secret.app["stripe-webhook-secret"].secret_id
            version = "latest"
          }
        }
      }

      volume_mounts {
        name       = "cloudsql"
        mount_path = "/cloudsql"
      }

      startup_probe {
        http_get {
          path = "/up"
          port = 8080
        }
        initial_delay_seconds = 5
        timeout_seconds       = 3
        period_seconds        = 10
        failure_threshold     = 12
      }

      liveness_probe {
        http_get {
          path = "/up"
          port = 8080
        }
        period_seconds    = 30
        timeout_seconds   = 3
        failure_threshold = 3
      }
    }

    volumes {
      name = "cloudsql"

      cloud_sql_instance {
        instances = [local.sql_connection_name]
      }
    }
  }

  traffic {
    type    = "TRAFFIC_TARGET_ALLOCATION_TYPE_LATEST"
    percent = 100
  }

  depends_on = [
    google_project_service.apis,
    google_project_iam_member.cloud_run,
    google_artifact_registry_repository_iam_member.cloud_run_reader,
    google_storage_bucket_iam_member.cloud_run_media,
    google_secret_manager_secret_iam_member.cloud_run_accessor,
    google_sql_database.app,
    google_sql_user.app,
    google_secret_manager_secret_version.app,
  ]
}

resource "google_cloud_run_v2_service_iam_member" "public_invoker" {
  count = var.allow_unauthenticated ? 1 : 0

  project  = var.project_id
  location = google_cloud_run_v2_service.api.location
  name     = google_cloud_run_v2_service.api.name
  role     = "roles/run.invoker"
  member   = "allUsers"
}
