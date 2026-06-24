<template>
  <DefaultLayout :user="user">
    <div class="container pt-4">
      <h1 class="mb-4">API Access Tokens</h1>
      <p class="text-muted mb-4">
        API tokens allow external applications (like the Google Slides add-on)
        to access ChimeIn on your behalf. Tokens should be kept secret.
      </p>

      <!-- Create Token -->
      <div class="card mb-4">
        <div class="card-header">Create New Token</div>
        <div class="card-body">
          <form @submit.prevent="createToken">
            <div class="form-group">
              <label for="token-name">Token Name</label>
              <div class="input-group">
                <input
                  id="token-name"
                  v-model="newTokenName"
                  type="text"
                  class="form-control"
                  placeholder="e.g. Google Slides Add-on"
                  maxlength="255"
                  required
                />
                <div class="input-group-append">
                  <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="creating || !newTokenName.trim()"
                  >
                    {{ creating ? "Creating..." : "Create Token" }}
                  </button>
                </div>
              </div>
            </div>
          </form>

          <!-- Newly Created Token Display -->
          <div v-if="newlyCreatedToken" class="alert alert-success mt-3">
            <strong>Token created!</strong> Copy this token now — it won't be
            shown again.
            <div class="input-group mt-2">
              <input
                type="text"
                class="form-control font-monospace"
                :value="newlyCreatedToken"
                readonly
              />
              <div class="input-group-append">
                <button
                  class="btn btn-outline-secondary"
                  type="button"
                  @click="copyToken"
                >
                  {{ copied ? "Copied!" : "Copy" }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Token List -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Your Tokens</span>
          <button
            v-if="tokens.length > 0"
            class="btn btn-sm btn-outline-danger"
            @click="revokeAll"
            :disabled="revokingAll"
          >
            {{ revokingAll ? "Revoking..." : "Revoke All" }}
          </button>
        </div>
        <div class="card-body">
          <div v-if="loading" class="text-center py-3">
            <span class="spinner-border spinner-border-sm" role="status"></span>
            Loading tokens...
          </div>

          <div v-else-if="tokens.length === 0" class="text-muted py-3 text-center">
            No API tokens yet. Create one above to get started.
          </div>

          <table v-else class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Created</th>
                <th>Last Used</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="token in tokens" :key="token.id">
                <td>{{ token.name }}</td>
                <td>{{ formatDate(token.created_at) }}</td>
                <td>{{ token.last_used_at ? formatDate(token.last_used_at) : "Never" }}</td>
                <td class="text-right">
                  <button
                    class="btn btn-sm btn-outline-danger"
                    @click="deleteToken(token)"
                    :disabled="token._deleting"
                  >
                    {{ token._deleting ? "Deleting..." : "Delete" }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="alert alert-danger mt-3">
        {{ error }}
      </div>
    </div>
  </DefaultLayout>
</template>

<script>
import DefaultLayout from "../../layouts/DefaultLayout.vue";

export default {
  components: { DefaultLayout },
  props: ["user"],
  data() {
    return {
      tokens: [],
      newTokenName: "",
      newlyCreatedToken: null,
      loading: true,
      creating: false,
      revokingAll: false,
      copied: false,
      error: null,
    };
  },
  mounted() {
    this.fetchTokens();
  },
  methods: {
    async fetchTokens() {
      this.loading = true;
      this.error = null;
      try {
        const res = await axios.get("/api/tokens");
        this.tokens = res.data.tokens.map((t) => ({ ...t, _deleting: false }));
      } catch (err) {
        this.error = "Failed to load tokens.";
        console.error(err);
      } finally {
        this.loading = false;
      }
    },

    async createToken() {
      if (!this.newTokenName.trim()) return;
      this.creating = true;
      this.error = null;
      this.newlyCreatedToken = null;
      try {
        const res = await axios.post("/api/tokens", {
          name: this.newTokenName.trim(),
        });
        this.newlyCreatedToken = res.data.token;
        this.newTokenName = "";
        await this.fetchTokens();
      } catch (err) {
        this.error = err.response?.data?.message || "Failed to create token.";
        console.error(err);
      } finally {
        this.creating = false;
      }
    },

    async deleteToken(token) {
      if (!confirm(`Delete token "${token.name}"? This cannot be undone.`)) {
        return;
      }
      token._deleting = true;
      this.error = null;
      try {
        await axios.delete(`/api/tokens/${token.id}`);
        this.tokens = this.tokens.filter((t) => t.id !== token.id);
      } catch (err) {
        this.error = "Failed to delete token.";
        token._deleting = false;
        console.error(err);
      }
    },

    async revokeAll() {
      if (
        !confirm(
          "Revoke ALL tokens? Any applications using them will lose access."
        )
      ) {
        return;
      }
      this.revokingAll = true;
      this.error = null;
      try {
        await axios.post("/api/tokens/revoke-all");
        this.tokens = [];
        this.newlyCreatedToken = null;
      } catch (err) {
        this.error = "Failed to revoke tokens.";
        console.error(err);
      } finally {
        this.revokingAll = false;
      }
    },

    copyToken() {
      navigator.clipboard.writeText(this.newlyCreatedToken).then(() => {
        this.copied = true;
        setTimeout(() => (this.copied = false), 2000);
      });
    },

    formatDate(dateStr) {
      if (!dateStr) return "";
      return new Date(dateStr).toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
  },
};
</script>

<style scoped>
.font-monospace {
  font-family: monospace;
  font-size: 0.85rem;
}
</style>
