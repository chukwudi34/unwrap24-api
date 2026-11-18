terraform {
  required_version = ">= 1.6.0"

  required_providers {
    google = {
      source  = "hashicorp/google"
      version = ">= 6.0, < 8.0"
    }
    random = {
      source  = "hashicorp/random"
      version = ">= 3.6, < 5.0"
    }
  }

  # Remote state is deliberately not configured here so that `terraform init
  # -backend=false` works for validation. For real deployments uncomment the
  # block below (or pass a backend config file) and keep state out of git:
  #
  # backend "gcs" {
  #   bucket = "your-terraform-state-bucket"
  #   prefix = "unwrap24/production"
  # }
}
