resource "random_password" "app_key" {
  count = var.laravel_app_key == null ? 1 : 0

  length  = 32
  special = false
}

locals {
  app_key = var.laravel_app_key != null ? var.laravel_app_key : "base64:${base64encode(random_password.app_key[0].result)}"

  secrets = {
    "app-key"               = local.app_key
    "db-password"           = random_password.db.result
    "google-client-secret"  = var.google_client_secret
    "stripe-secret-key"     = var.stripe_secret_key
    "stripe-webhook-secret" = var.stripe_webhook_secret
  }
}

resource "google_secret_manager_secret" "app" {
  for_each = local.secrets

  project   = var.project_id
  secret_id = "${local.name_prefix}-${each.key}"

  replication {
    auto {}
  }

  labels = local.labels

  depends_on = [google_project_service.apis]
}

resource "google_secret_manager_secret_version" "app" {
  for_each = local.secrets

  secret      = google_secret_manager_secret.app[each.key].id
  secret_data = each.value
}
