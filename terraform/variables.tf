variable "project_id" {
  description = "Google Cloud project ID that hosts all Unwrap24 infrastructure."
  type        = string

  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{4,28}[a-z0-9]$", var.project_id))
    error_message = "project_id must be a valid Google Cloud project ID."
  }
}

variable "region" {
  description = "Region for Artifact Registry, Cloud Run, Cloud SQL and Cloud Storage."
  type        = string
  default     = "us-central1"
}

variable "environment" {
  description = "Deployment environment name, used in resource names and labels."
  type        = string
  default     = "production"
}

variable "app_name" {
  description = "Application name, used as a naming and labelling prefix."
  type        = string
  default     = "unwrap24"
}

variable "labels" {
  description = "Additional labels applied to supporting resources."
  type        = map(string)
  default     = {}
}

# -----------------------------------------------------------------------------
# Artifact Registry / container image
# -----------------------------------------------------------------------------

variable "artifact_registry_repository_id" {
  description = "Artifact Registry Docker repository ID."
  type        = string
  default     = "unwrap24"
}

variable "service_name" {
  description = "Cloud Run service name."
  type        = string
  default     = "unwrap24-api"
}

variable "image_tag" {
  description = "Container image tag deployed to Cloud Run."
  type        = string
  default     = "latest"
}

# -----------------------------------------------------------------------------
# Cloud Run
# -----------------------------------------------------------------------------

variable "cloud_run_service_account_id" {
  description = "Account ID of the dedicated Cloud Run runtime service account."
  type        = string
  default     = "unwrap24-run"
}

variable "container_cpu" {
  description = "Cloud Run container CPU limit."
  type        = string
  default     = "1"
}

variable "container_memory" {
  description = "Cloud Run container memory limit."
  type        = string
  default     = "512Mi"
}

variable "container_concurrency" {
  description = "Maximum concurrent requests per container instance."
  type        = number
  default     = 80
}

variable "min_instance_count" {
  description = "Minimum Cloud Run instances (0 allows scale to zero)."
  type        = number
  default     = 0
}

variable "max_instance_count" {
  description = "Maximum Cloud Run instances."
  type        = number
  default     = 10
}

variable "allow_unauthenticated" {
  description = "Expose the API publicly (required for the Next.js frontend and public calendar pages)."
  type        = bool
  default     = true
}

# -----------------------------------------------------------------------------
# Cloud SQL
# -----------------------------------------------------------------------------

variable "db_instance_name" {
  description = "Cloud SQL instance name."
  type        = string
  default     = "unwrap24-mysql"
}

variable "db_name" {
  description = "MySQL database name."
  type        = string
  default     = "unwrap24"
}

variable "db_user" {
  description = "MySQL application user."
  type        = string
  default     = "unwrap24"
}

variable "db_tier" {
  description = "Cloud SQL machine tier."
  type        = string
  default     = "db-f1-micro"
}

variable "db_disk_size_gb" {
  description = "Initial Cloud SQL disk size in GB (auto-resizes)."
  type        = number
  default     = 20
}

variable "db_availability_type" {
  description = "Cloud SQL availability type: ZONAL or REGIONAL."
  type        = string
  default     = "ZONAL"

  validation {
    condition     = contains(["ZONAL", "REGIONAL"], var.db_availability_type)
    error_message = "db_availability_type must be ZONAL or REGIONAL."
  }
}

variable "db_backup_enabled" {
  description = "Enable automated Cloud SQL backups."
  type        = bool
  default     = true
}

variable "db_deletion_protection" {
  description = "Prevent accidental destruction of the Cloud SQL instance."
  type        = bool
  default     = true
}

# -----------------------------------------------------------------------------
# Cloud Storage
# -----------------------------------------------------------------------------

variable "media_bucket_name" {
  description = "Globally unique bucket name for calendar media. Empty derives <project>-<app>-<env>-media."
  type        = string
  default     = ""
}

variable "bucket_force_destroy" {
  description = "Allow bucket deletion even when it still contains objects."
  type        = bool
  default     = false
}

variable "media_retention_days" {
  description = "Delete archived media older than this many days. 0 disables the rule."
  type        = number
  default     = 0
}

# -----------------------------------------------------------------------------
# Application settings
# -----------------------------------------------------------------------------

variable "app_url" {
  description = "Public API base URL. Empty derives the Cloud Run run.app URL."
  type        = string
  default     = ""
}

variable "frontend_url" {
  description = "Frontend origin allowed by CORS and used for auth redirects."
  type        = string
  default     = "https://unwrap24.com"
}

variable "laravel_app_key" {
  description = "Existing APP_KEY (base64:...). Empty generates a random key stored in Secret Manager."
  type        = string
  default     = null
  sensitive   = true
}

variable "log_level" {
  description = "Laravel log level for production."
  type        = string
  default     = "info"
}

variable "log_retention_days" {
  description = "Retention of the dedicated Cloud Logging bucket."
  type        = number
  default     = 30
}

variable "google_client_id" {
  description = "Google OAuth client ID (public, not a secret)."
  type        = string
  default     = ""
}

variable "google_client_secret" {
  description = "Google OAuth client secret (stored in Secret Manager)."
  type        = string
  default     = ""
  sensitive   = true
}

variable "stripe_secret_key" {
  description = "Stripe secret API key (stored in Secret Manager)."
  type        = string
  default     = ""
  sensitive   = true
}

variable "stripe_webhook_secret" {
  description = "Stripe webhook signing secret (stored in Secret Manager)."
  type        = string
  default     = ""
  sensitive   = true
}

variable "stripe_publishable_key" {
  description = "Stripe publishable key (public, injected as a plain env var)."
  type        = string
  default     = ""
}

variable "mail_mailer" {
  description = "Laravel mail mailer used in production."
  type        = string
  default     = "log"
}

variable "mail_from_address" {
  description = "Default sender address for transactional mail."
  type        = string
  default     = "hello@unwrap24.com"
}
