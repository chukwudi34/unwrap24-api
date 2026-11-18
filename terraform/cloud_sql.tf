resource "random_password" "db" {
  length  = 32
  special = false
}

resource "google_sql_database_instance" "main" {
  project             = var.project_id
  name                = var.db_instance_name
  database_version    = "MYSQL_8_0"
  region              = var.region
  deletion_protection = var.db_deletion_protection

  settings {
    tier              = var.db_tier
    availability_type = var.db_availability_type
    disk_size         = var.db_disk_size_gb
    disk_type         = "PD_SSD"
    disk_autoresize   = true
    user_labels       = local.labels

    backup_configuration {
      enabled                        = var.db_backup_enabled
      start_time                     = "03:00"
      point_in_time_recovery_enabled = false
      backup_retention_settings {
        retained_backups = 7
      }
    }

    ip_configuration {
      # Public IP is only reachable through the Cloud SQL connector used by
      # Cloud Run (no authorized networks are configured) and TLS is required.
      ipv4_enabled = true
      ssl_mode     = "ENCRYPTED_ONLY"
    }

    maintenance_window {
      day  = 7
      hour = 4
    }
  }

  depends_on = [google_project_service.apis]
}

resource "google_sql_database" "app" {
  project   = var.project_id
  name      = var.db_name
  instance  = google_sql_database_instance.main.name
  charset   = "utf8mb4"
  collation = "utf8mb4_unicode_ci"
}

resource "google_sql_user" "app" {
  project  = var.project_id
  name     = var.db_user
  instance = google_sql_database_instance.main.name
  password = random_password.db.result
}
