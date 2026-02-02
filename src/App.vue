<template>
  <NcContent app-name="dtcassociations" class="app-dtcassociations">
    <NcAppNavigation slot="navigation">
      <NcAppNavigationItem id="associations" name="Associations" :title="t('dtcassociations', 'Associations')"
        icon="icon-category-organization" :active="!selectedAssociation" @click="selectedAssociation = null" />
      <NcAppNavigationItem v-if="selectedAssociation" :id="'asso-' + selectedAssociation.id"
        :name="selectedAssociation.name" :title="selectedAssociation.name" icon="icon-user-group" :active="true" />
    </NcAppNavigation>

    <NcAppContent>
      <transition name="fade">
        <div v-if="notification.message" class="dtc-toast" :class="'toast-' + notification.type">
          <span :class="notification.type === 'error' ? 'icon-error' : 'icon-checkmark-white'"></span>
          <span class="toast-message">{{ notification.message }}</span>
          <span class="icon-close toast-close" @click="closeNotification"></span>
        </div>
      </transition>

      <div v-if="!selectedAssociation" class="dtc-container">
        <div class="header-title-row">
          <h2 class="app-title no-margin">{{ t('dtcassociations', 'Gestion Associations') }}</h2>
          <span v-if="!loading && associations.length > 0" class="association-counter">
            {{ associations.length }} {{ associations.length > 1 ? t('dtcassociations', 'associations présentes') :
              t('dtcassociations', 'association présente') }}
          </span>
        </div>
        <div v-if="canManage" class="add-form-container">
          <div class="add-form-association">
            <label for="newAssocName">{{ t('dtcassociations', 'Création d\'une association') }}</label>

            <div class="add-form-input-container">
              <input v-model="newAssocName" id="newAssocName" type="text" class="dtc-input"
                :class="{ 'input-error': creationError }"
                :placeholder="t('dtcassociations', 'Nom de la nouvelle association...')" maxlength="50"
                @keyup.enter="createAssociation" @input="creationError = ''" />
              <NcButton type="primary" class="btn-orange" @click="createAssociation" :disabled="loading">
                {{ t('dtcassociations', 'Ajouter') }}
              </NcButton>
            </div>
          </div>
          <div v-if="creationError" class="error-text">
            {{ creationError }}
          </div>

          <p class="help-text">
            {{ t('dtcassociations', 'Autorisé : Lettres, accents, chiffres, espaces, tiret, tiret du bas, apostrophe')
            }}
          </p>
        </div>
        <div v-if="loading" class="icon-loading"></div>
        <ul v-else class="association-list">
          <li v-for="assoc in associations" :key="assoc.id" class="association-item clickable"
            @click="selectAssociation(assoc)">
            <span class="icon-category-organization icon-white"></span>
            <div class="info">
              <div class="name-container">
                <div class="name-row">
                  <span class="name">{{ assoc.name }}</span>
                  <span v-if="assoc.member_count !== undefined" class="member-count">
                    ({{ assoc.member_count }} {{ assoc.member_count > 1 ? 'membres' : 'membre' }})
                  </span>
                </div>
                <span class="quota-badge"
                  :class="{ 'quota-warning': assoc.quota > 0 && calculatePercentage(assoc.usage, assoc.quota) > 80 }">
                  {{ formatSize(assoc.usage) }} / {{ formatQuota(assoc.quota) }}
                </span>
              </div>
            </div>
            <NcActions :primary="true" menu-name="Actions" @click.stop>
              <NcActionButton class="btn-orange" @click.stop="openRenameModal(assoc)" icon="icon-rename"
                :close-after-click="true">
                {{ t('dtcassociations', 'Renommer l\'association') }}
              </NcActionButton>
              <NcActionButton v-if="canDelete" @click.stop="openDeleteModal(assoc)" icon="icon-delete"
                :close-after-click="true">
                {{ t('dtcassociations', 'Supprimer l\'association') }}
              </NcActionButton>
            </NcActions>
          </li>
          <li v-if="associations.length === 0" class="empty-state">
            {{ t('dtcassociations', 'Aucune association trouvée.') }}
          </li>
        </ul>
      </div>
      <div v-else class="dtc-container">
        <div class="header-actions">
          <NcButton @click="selectedAssociation = null" type="tertiary" icon="icon-arrow-left-active">
            {{ t('dtcassociations', 'Retour') }}
          </NcButton>

          <div class="header-title-block">
            <h2 class="app-title no-margin">{{ selectedAssociation.name }}</h2>
            <div class="quota-detail">
              <span class="icon-quota"></span>
              <span class="quota-text">
                Utilisation : <strong>{{ formatSize(selectedAssociation.usage) }}</strong>
                sur {{ formatQuota(selectedAssociation.quota) }}
                <span v-if="selectedAssociation.quota > 0">
                  ({{ calculatePercentage(selectedAssociation.usage, selectedAssociation.quota) }}%)
                </span>
              </span>

              <div class="progress-bar-bg" v-if="selectedAssociation.quota > 0">
                <div class="progress-bar-fill"
                  :style="{ width: calculatePercentage(selectedAssociation.usage, selectedAssociation.quota) + '%' }">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="add-form">
          <div class="user-select-container">
            <label for="user-select">{{ t('dtcassociations', 'Ajouter un membre') }}</label>
            <NcMultiselect id="user-select" v-model="selectedUser" :options="userOptions" :loading="isLoadingUsers"
              label="label" track-by="id" searchable :internal-search="false" @search-change="searchUsers"
              :placeholder="t('dtcassociations', 'Rechercher un utilisateur...')" class="add-user-select">
              <template #noOptions>
                {{ t('dtcassociations', 'Écrivez pour rechercher') }}
              </template>

              <template #noResult>
                {{ t('dtcassociations', 'Aucun utilisateur trouvé') }}
              </template>
            </NcMultiselect>
          </div>
          <select v-model="newMemberRole" class="dtc-select">
            <option value="president">Président / Vice-Président</option>
            <option value="treasurer">Trésorier / Vice-Trésorier</option>
            <option value="secretary">Secrétaire / Vice-Secrétaire</option>
            <option value="teacher">Enseignant</option>
            <option v-if="canManage" value="invite">Invité</option>
            <option v-if="canDelete" value="admin_iut">Admin IUT</option>
          </select>
          <NcButton type="primary" class="btn-orange" @click="addMember" :disabled="membersLoading || !selectedUser">
            {{ t('dtcassociations', 'Ajouter') }}
          </NcButton>
        </div>
        <div v-if="membersLoading" class="icon-loading"></div>
        <ul v-else class="association-list">
          <li v-for="member in members" :key="member.id" class="association-item">
            <span class="icon-user icon-white"></span>
            <div class="info" v-if="editingMemberId !== member.user_id">
              <span class="name" :title="member.user_id">{{ member.display_name || member.user_id }}</span>
              <span class="role-badge">{{ translateRole(member.role) }}</span>
            </div>
            <div class="info edit-mode" v-else>
              <div class="user">
                <span class="name" :title="member.user_id">{{ member.display_name || member.user_id }}</span>
                <select v-model="editingMemberRole" class="dtc-select-small" @click.stop>
                  <option value="president">Président / Vice-Président</option>
                  <option value="treasurer">Trésorier / Vice-Trésorier</option>
                  <option value="secretary">Secrétaire / Vice-Secrétaire</option>
                  <option value="teacher">Enseignant</option>
                  <option v-if="canManage" value="invite">Invité</option>
                  <option v-if="canDelete" value="admin_iut">Admin IUT</option>
                </select>
              </div>
              <div class="actions">
                <NcButton type="primary" class="btn-orange" @click.stop="saveMemberRole(member)" icon="icon-checkmark">
                  Valider le changement de rôle
                </NcButton>
                <NcButton type="tertiary" class="btn-cancel" @click.stop="cancelEditMember" icon="icon-close">Annuler
                </NcButton>
              </div>
            </div>
            <NcActions :primary="true" menu-name="Actions" v-if="editingMemberId !== member.user_id">
              <NcActionButton
                v-if="!(member.user_id === currentUserId && (member.role === 'president' || member.role === 'admin_iut'))"
                @click="startEditMember(member)" icon="icon-rename" :close-after-click="true">
                {{ t('dtcassociations', 'Modifier le rôle') }}
              </NcActionButton>

              <NcActionButton
                v-if="!(member.user_id === currentUserId && (member.role === 'president' || member.role === 'admin_iut'))"
                @click="openRemoveMemberModal(member)" icon="icon-delete" :close-after-click="true">
                {{ t('dtcassociations', 'Supprimer le membre') }}
              </NcActionButton>
            </NcActions>
          </li>
          <li v-if="members.length === 0" class="empty-state">
            {{ t('dtcassociations', 'Aucun membre dans cette association.') }}
          </li>
        </ul>
      </div>
      <NcModal v-if="showDeleteModal" @close="closeDeleteModal" title="Suppression définitive" size="small">
        <div class="modal-content">
          <p><strong>Attention :</strong> Vous êtes sur le point de supprimer l'association <em>{{
            associationToDelete?.name
              }}</em>.</p>
          <p class="warning-text">Cette action est irréversible.</p>
        </div>
        <div class="modal-footer-custom">
          <NcButton @click="closeDeleteModal">Annuler</NcButton>
          <NcButton @click="confirmDeleteAssociation" type="error">Confirmer la suppression</NcButton>
        </div>
      </NcModal>
      <NcModal v-if="showRenameModal" @close="closeRenameModal" title="Renommer l'association" size="small">
        <div class="modal-content">
          <p>Entrez le nouveau nom pour l'association :</p>

          <input v-model="renameInput" type="text" class="dtc-input full-width" :class="{ 'input-error': renameError }"
            maxlength="50" @keyup.enter="confirmRenameAssociation" @input="renameError = ''" ref="renameInput" />

          <div v-if="renameError" class="error-text">
            {{ renameError }}
          </div>

          <p class="help-text" style="margin-bottom: 10px;">
            {{ t('dtcassociations', 'Autorisé : Lettres, accents, chiffres, espaces, tiret, tiret du bas, apostrophe')
            }}
          </p>

          <p class="info-text">Le dossier d'équipe sera également renommé.</p>
        </div>
        <div class="modal-footer-custom">
          <NcButton @click="closeRenameModal">Annuler</NcButton>
          <NcButton @click="confirmRenameAssociation" type="primary" class="btn-orange">Valider le renommage</NcButton>
        </div>
      </NcModal>
      <NcModal v-if="showRemoveMemberModal" @close="closeRemoveMemberModal" title="Retirer un membre" size="small">
        <div class="modal-content">
          <p>Voulez-vous vraiment retirer <strong>{{ memberToRemove?.display_name || memberToRemove?.user_id }}</strong>
            de
            cette association ?</p>
          <p class="warning-text">Il perdra l'accès au dossier d'équipe.</p>
        </div>
        <div class="modal-footer-custom">
          <NcButton @click="closeRemoveMemberModal">Annuler</NcButton>
          <NcButton @click="confirmRemoveMember" type="error">Supprimer le membre</NcButton>
        </div>
      </NcModal>

    </NcAppContent>
  </NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent';
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation';
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem';
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent';
import NcButton from '@nextcloud/vue/dist/Components/NcButton';
import NcActions from '@nextcloud/vue/dist/Components/NcActions';
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton';
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect';
import NcModal from '@nextcloud/vue/dist/Components/NcModal';
import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router';

export default {
  name: 'App',
  components: {
    NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent,
    NcButton, NcActions, NcActionButton, NcMultiselect, NcModal
  },
  data() {
    return {
      associations: [],
      newAssocName: '',
      creationError: '',
      renameError: '',
      loading: false,
      selectedAssociation: null,
      members: [],
      membersLoading: false,
      selectedUser: null,
      userOptions: [],
      isLoadingUsers: false,
      newMemberRole: 'president',

      showDeleteModal: false,
      associationToDelete: null,
      showRenameModal: false,
      associationToRename: null,
      renameInput: '',
      showRemoveMemberModal: false,
      memberToRemove: null,
      editingMemberId: null,
      editingMemberRole: 'member',

      isAdmin: false,
      currentUserId: '',
      canDelete: false,
      canManage: false,

      // Système de notification
      notification: {
        message: '',
        type: 'error' // 'error' ou 'success'
      },
      notificationTimeout: null
    };
  },
  mounted() {
    if (window.OC && window.OC.getCurrentUser) {
      this.currentUserId = window.OC.getCurrentUser().uid;
    }
    this.checkPermissions();
    this.fetchAssociations();
    try {
      if (window.OC && window.OC.isUserAdmin) this.isAdmin = window.OC.isUserAdmin();
    } catch (e) { }
  },
  methods: {
    // Méthode pour afficher les notifications
    showNotification(message, type = 'error') {
      if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
      this.notification = { message, type };
      this.notificationTimeout = setTimeout(() => {
        this.closeNotification();
      }, 5000);
    },
    closeNotification() {
      this.notification.message = '';
      if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
    },

    async checkPermissions() {
      try {
        const response = await axios.get(generateUrl('/apps/dtcassociations/api/1.0/user/permissions'));
        this.canDelete = response.data.canDelete;
        this.canManage = response.data.canManage;
      } catch { this.canDelete = false; this.canManage = false; }
    },
    async fetchAssociations() {
      this.loading = true;
      try {
        const response = await axios.get(generateUrl('/apps/dtcassociations/api/1.0/associations'));
        this.associations = response.data;
      } catch (e) { console.error(e); } finally { this.loading = false; }
    },
    async createAssociation() {
      this.creationError = '';
      if (!this.newAssocName.trim()) return;

      const forbiddenPattern = /[^\p{L}0-9 _'-]/u;
      if (forbiddenPattern.test(this.newAssocName)) {
        this.creationError = t('dtcassociations', 'Seuls les lettres, chiffres, tirets, tirets du bas, apostrophes et espaces sont autorisés.');
        return;
      }

      this.loading = true;
      try {
        const code = this.newAssocName.toLowerCase().replace(/[^a-z0-9]/g, '');
        await axios.post(generateUrl('/apps/dtcassociations/api/1.0/associations'), { name: this.newAssocName, code: code });
        this.newAssocName = '';
        await this.fetchAssociations();
        this.showNotification(t('dtcassociations', 'Association créée avec succès'), 'success');
      } catch (e) {
        console.error(e);
        this.creationError = t('dtcassociations', 'Erreur lors de la création (nom déjà pris ?)');
      } finally {
        this.loading = false;
      }
    },
    openDeleteModal(assoc) {
      this.associationToDelete = assoc;
      this.showDeleteModal = true;
    },
    closeDeleteModal() {
      this.showDeleteModal = false;
      this.associationToDelete = null;
    },
    async confirmDeleteAssociation() {
      if (!this.associationToDelete) return;
      const id = this.associationToDelete.id;
      this.showDeleteModal = false;
      this.loading = true;
      try {
        await axios.delete(generateUrl(`/apps/dtcassociations/api/1.0/associations/${id}`));
        if (this.selectedAssociation?.id === id) this.selectedAssociation = null;
        await this.fetchAssociations();
        this.showNotification(t('dtcassociations', 'Association supprimée avec succès'), 'success');
      } catch {
        this.showNotification(t('dtcassociations', 'Erreur lors de la suppression de l\'association'), 'error');
      } finally { this.loading = false; }
    },
    openRenameModal(assoc) {
      this.associationToRename = assoc;
      this.renameInput = assoc.name;
      this.renameError = '';
      this.showRenameModal = true;
      this.$nextTick(() => { if (this.$refs.renameInput) this.$refs.renameInput.focus(); });
    },
    closeRenameModal() {
      this.showRenameModal = false;
      this.associationToRename = null;
      this.renameInput = '';
    },
    async confirmRenameAssociation() {
      this.renameError = '';
      if (!this.associationToRename || !this.renameInput.trim()) return;

      const forbiddenPattern = /[^\p{L}0-9 _'-]/u;
      if (forbiddenPattern.test(this.renameInput)) {
        this.renameError = t('dtcassociations', 'Seuls les lettres, chiffres, tirets, tirets du bas, apostrophes et espaces sont autorisés.');
        return;
      }

      const id = this.associationToRename.id;
      const newName = this.renameInput;
      this.loading = true;

      try {
        await axios.put(generateUrl(`/apps/dtcassociations/api/1.0/associations/${id}`), { name: newName });
        await this.fetchAssociations();
        if (this.selectedAssociation?.id === id) {
          this.selectedAssociation.name = newName;
        }
        this.showRenameModal = false;
        this.showNotification(t('dtcassociations', 'Association renommée avec succès'), 'success');
      } catch (e) {
        console.error(e);
        this.renameError = t('dtcassociations', 'Erreur : ce nom est peut-être déjà utilisé ou invalide.');
      } finally {
        this.loading = false;
      }
    },
    selectAssociation(assoc) {
      this.selectedAssociation = assoc;
      this.fetchMembers();
    },
    async fetchMembers() {
      if (!this.selectedAssociation) return;
      this.membersLoading = true;
      try {
        const response = await axios.get(generateUrl(`/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members`));
        this.members = response.data;
      } catch (e) { console.error(e); } finally { this.membersLoading = false; }
    },
    async searchUsers(query) {
      if (!query || query.length < 2) return;
      this.isLoadingUsers = true;
      try {
        const url = window.OC.linkToOCS('apps/files_sharing/api/v1', 2) + 'sharees';
        const response = await axios.get(url, { params: { search: query, itemType: 'file', format: 'json', perPage: 20 } });
        const users = response.data.ocs?.data?.users || [];
        this.userOptions = users.map(u => ({ id: u.value.shareWith, label: u.label }));
      } catch (e) { console.error(e); } finally { this.isLoadingUsers = false; }
    },
    async addMember() {
      if (!this.selectedUser) return;
      await this.updateMemberRoleCall(this.selectedUser.id, this.newMemberRole, true);
      this.selectedUser = null;
    },
    startEditMember(member) {
      this.editingMemberId = member.user_id;
      this.editingMemberRole = member.role;
    },
    cancelEditMember() {
      this.editingMemberId = null;
    },
    async saveMemberRole(member) {
      if (this.editingMemberRole !== member.role) {
        await this.updateMemberRoleCall(member.user_id, this.editingMemberRole, false);
      }
      this.cancelEditMember();
    },
    async updateMemberRoleCall(userId, role, isAdd) {
      this.membersLoading = true;
      try {
        await axios.post(generateUrl(`/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members`), {
          userId: userId, role: role
        });
        await this.fetchMembers();
        this.showNotification(isAdd ? t('dtcassociations', 'Membre ajouté avec succès') : t('dtcassociations', 'Rôle mis à jour'), 'success');
      } catch {
        this.showNotification(t('dtcassociations', 'Erreur lors de la sauvegarde'), 'error');
      } finally { this.membersLoading = false; }
    },
    openRemoveMemberModal(member) {
      this.memberToRemove = member;
      this.showRemoveMemberModal = true;
    },
    closeRemoveMemberModal() {
      this.showRemoveMemberModal = false;
      this.memberToRemove = null;
    },
    async confirmRemoveMember() {
      if (!this.memberToRemove) return;
      const userId = this.memberToRemove.user_id;
      this.showRemoveMemberModal = false;
      this.membersLoading = true;
      try {
        await axios.delete(generateUrl(`/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members/${userId}`));
        await this.fetchMembers();
        this.showNotification(t('dtcassociations', 'Membre retiré avec succès'), 'success');
      } catch {
        this.showNotification(t('dtcassociations', 'Erreur lors de la suppression du membre'), 'error');
      } finally { this.membersLoading = false; }
    },
    formatSize(bytes) {
      if (bytes === undefined || bytes === null) return '0 B';
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },
    calculatePercentage(usage, quota) {
      if (quota < 0) return 0;
      if (!quota || quota === 0) return 100;
      let percent = (usage / quota) * 100;
      return Math.min(percent, 100).toFixed(1);
    },
    formatQuota(quota) {
      if (quota < 0) return this.t('dtcassociations', 'Illimité');
      return this.formatSize(quota);
    },
    translateRole(role) {
      const roles = {
        'president': 'Président / Vice-Président',
        'treasurer': 'Trésorier / Vice-Trésorier',
        'secretary': 'Secrétaire / Vice-Secrétaire',
        'teacher': 'Enseignant',
        'admin_iut': 'Admin IUT',
        'invite': 'Invité'
      };
      return roles[role] || role;
    }
  }
};
</script>

<style>
@font-face {
  font-family: 'Luciole';
  src: url('@/assets/fonts/Luciole-Regular.ttf') format('truetype');
  font-weight: normal;
  font-style: normal;
}

:root {
  --color-dtc-primary: #282F63;
  --color-dtc-secondary: #D4451B;
  --color-dtc-text: #fff;
}

.app-content {
  background: var(--color-dtc-primary) !important;
}

.app-dtcassociations,
.app-dtcassociations * {
  font-family: 'Luciole', sans-serif !important;
}

.button-vue--vue-primary {
  background-color: var(--color-dtc-secondary) !important;
  color: var(--color-dtc-text) !important;
}

.modal-container {
  background: var(--color-dtc-primary) !important;
  padding: 20px !important;
}

.modal-container__content {
  display: flex;
  flex-direction: column;
  text-align: center;
  align-items: center;
}
</style>

<style scoped>
/* Toast Notification Styles */
.dtc-toast {
  position: fixed;
  top: 60px;
  right: 20px;
  z-index: 10000;
  padding: 12px 20px;
  border-radius: var(--border-radius-large);
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  color: #fff;
  font-weight: bold;
  max-width: 400px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.dtc-toast.toast-error {
  background-color: var(--color-dtc-secondary);
}

.dtc-toast.toast-success {
  background-color: #46ba61;
}

.toast-message {
  flex-grow: 1;
  font-size: 0.95em;
}

.toast-close {
  cursor: pointer;
  opacity: 0.8;
  font-size: 1.2em;
}

.toast-close:hover {
  opacity: 1;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s, transform 0.4s;
}

.fade-enter,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}


.app-dtcassociations {
  margin: 0;
}

.dtc-container {
  padding: 20px;
}

.app-title {
  color: var(--color-dtc-text);
  font-weight: bold;
  margin-bottom: 20px;
}

.header-title-row {
  display: flex;
  align-items: baseline;
  gap: 15px;
  margin-bottom: 20px;
}

.association-counter {
  font-size: 0.9em;
  color: #ddd;
  font-weight: normal;
  font-style: italic;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 20px;
}

.add-form {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 20px;
}

.dtc-input {
  flex-grow: 1;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  box-sizing: border-box;
  height: 45px !important;
}

.dtc-input.full-width {
  width: 100%;
  margin: 10px 0;
}

.user-select-container {
  display: flex;
  flex-direction: column;
  flex-grow: 1;
  min-width: 200px;
  gap: 6px;
}

.add-user-select {
  width: 17rem;
}

.dtc-select {
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
  color: var(--color-text-main);
  height: 44px;
}

.dtc-select-small {
  padding: 5px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
  color: var(--color-text-main);
  margin-right: 5px;
}

.association-list {
  list-style: none;
  padding: 0;
}

.association-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px;
  color: var(--color-dtc-text);
  border-radius: var(--border-radius-large);
  background: rgba(0, 0, 0, 0.4);
  margin-bottom: 10px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.association-item.clickable {
  cursor: pointer;
}

.association-item.clickable:hover {
  opacity: 0.95;
}

.association-item .icon {
  font-size: 24px;
  opacity: 1;
  color: var(--color-dtc-text);
}

.association-item .icon-white {
  filter: brightness(0) invert(1);
  cursor: pointer;
}

.association-item .info {
  flex-grow: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.association-item .info.edit-mode {
  gap: 5px;
  justify-content: space-between;
}

.user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.actions {
  display: flex;
  gap: 5px;
}

.role-badge {
  background-color: rgba(255, 255, 255, 0.2);
  color: #fff;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.85em;
  font-weight: bold;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.modal-content {
  padding: 20px;
}

.modal-footer-custom {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.warning-text {
  color: var(--color-dtc-secondary);
  margin-top: 10px;
}

.info-text {
  color: var(--color-text-maxcontrast);
  font-size: 0.9em;
  font-style: italic;
}

::v-deep .btn-orange {
  background-color: var(--color-dtc-secondary) !important;
  border-color: var(--color-dtc-secondary) !important;
  color: #fff !important;
}

::v-deep .btn-orange:hover,
::v-deep .btn-orange:focus {
  background-color: #b83a15 !important;
  border-color: #b83a15 !important;
}

.add-form-container {
  margin: 30px 0;
  display: flex;
  flex-direction: column;
}

.dtc-input.input-error {
  border-color: var(--color-dtc-secondary);
  box-shadow: 0 0 0 1px var(--color-dtc-secondary);
}

.error-text {
  color: var(--color-dtc-secondary);
  font-size: 0.9em;
  font-weight: bold;
  margin-top: 2px;
  margin-left: 5px;
}

.help-text {
  color: rgb(244, 241, 241);
  font-size: 0.85em;
  margin-top: 2px;
  margin-left: 5px;
  font-style: italic;
}

.name-container {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.quota-badge {
  font-size: 0.75em;
  color: white;
  opacity: 0.9;
  margin-top: 2px;
  cursor: pointer;
}

.quota-badge.quota-warning {
  color: var(--color-dtc-secondary);
  font-weight: bold;
  opacity: 1;
}

.header-title-block {
  display: flex;
  flex-direction: column;
}

.no-margin {
  margin: 0;
}

.quota-detail {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 5px;
  font-size: 0.9em;
  color: var(--color-dtc-text);
  opacity: 0.9;
}

.icon-quota {
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23FFFFFF'%3E%3Cpath d='M12 2C6.48 2 2 4.02 2 6.5S6.48 11 12 11s10-2.02 10-4.5S17.52 2 12 2zm0 9c-5.52 0-10-2.02-10-4.5V10c0 2.48 4.48 4.5 10 4.5s10-2.02 10-4.5V6.5C22 8.98 17.52 11 12 11zm0 5c-5.52 0-10-2.02-10-4.5V15c0 2.48 4.48 4.5 10 4.5s10-2.02 10-4.5v-3.5c0 2.48-4.48 4.5-10 4.5z'/%3E%3C/svg%3E");
  width: 20px;
  height: 20px;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  display: inline-block;
}

.progress-bar-bg {
  width: 100px;
  height: 6px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 3px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: var(--color-dtc-secondary);
  transition: width 0.3s ease;
}

.name {
  cursor: pointer;
}

.add-form-association {
  display: flex;
  flex-direction: column;
  margin-bottom: 5px;
  gap: 3px;
  width: 100%;
}

.add-form-input-container {
  display: flex;
  gap: 0.5rem;
}

.name-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.member-count {
  font-size: 0.85em;
  color: #ddd;
  font-weight: normal;
  font-style: italic;
  cursor: pointer;
}
</style>