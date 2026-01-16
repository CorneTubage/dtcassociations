<template>
  <NcContent app-name="dtcassociations" class="app-dtcassociations">
    <NcAppNavigation slot="navigation">
      <NcAppNavigationItem
        id="associations"
        name="Associations"
        :title="t('dtcassociations', 'Associations')"
        icon="icon-category-organization"
        :active="!selectedAssociation"
        @click="selectedAssociation = null"
      />
      <NcAppNavigationItem
        v-if="selectedAssociation"
        :id="'asso-' + selectedAssociation.id"
        :name="selectedAssociation.name"
        :title="selectedAssociation.name"
        icon="icon-user-group"
        :active="true"
      />
    </NcAppNavigation>

    <NcAppContent>
      <div v-if="!selectedAssociation" class="dtc-container">
        <h2 class="app-title">{{ t('dtcassociations', 'Gestion Associations') }}</h2>

        <div class="add-form" v-if="canDelete">
          <input
            v-model="newAssocName"
            type="text"
            class="dtc-input"
            :placeholder="t('dtcassociations', 'Nom de la nouvelle association...')"
            @keyup.enter="createAssociation"
          />
          <NcButton type="primary" class="btn-orange" @click="createAssociation" :disabled="loading">
            {{ t('dtcassociations', 'Ajouter') }}
          </NcButton>
        </div>

        <div v-if="loading" class="icon-loading"></div>

        <ul v-else class="association-list">
          <li 
            v-for="assoc in associations" 
            :key="assoc.id" 
            class="association-item clickable"
            @click="selectAssociation(assoc)"
          >
            <span class="icon-category-organization icon-white"></span>
            <div class="info">
              <span class="name">{{ assoc.name }}</span>
            </div>
            
            <NcActions :primary="true" menu-name="Actions" @click.stop>
              <NcActionButton class="btn-orange" @click.stop="openRenameModal(assoc)" icon="icon-rename" :close-after-click="true">
                {{ t('dtcassociations', 'Renommer') }}
              </NcActionButton>
              <NcActionButton 
                v-if="canDelete"
                @click.stop="openDeleteModal(assoc)" 
                icon="icon-delete" 
                :close-after-click="true"
              >
                {{ t('dtcassociations', 'Supprimer') }}
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
           <h2 class="app-title">{{ selectedAssociation.name }} - Membres</h2>
        </div>

        <div class="add-form">
          <div class="user-select-container">
            <NcMultiselect
              v-model="selectedUser"
              :options="userOptions"
              :loading="isLoadingUsers"
              :placeholder="t('dtcassociations', 'Rechercher un utilisateur...')"
              label="label"
              track-by="id"
              :searchable="true"
              @search-change="searchUsers"
            />
          </div>

          <select v-model="newMemberRole" class="dtc-select">
            <option value="member">Membre</option>
            <option value="president">Président</option>
            <option value="treasurer">Trésorier</option>
            <option value="secretary">Secrétaire</option>
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
              <span class="name">{{ member.user_id }}</span>
              <span class="role-badge">{{ translateRole(member.role) }}</span>
            </div>

            <div class="info edit-mode"  v-else>
              <div class="user">
                <span class="name">{{ member.user_id }}</span>
                <select v-model="editingMemberRole" class="dtc-select-small" @click.stop>
                  <option value="member">Membre</option>
                  <option value="president">Président</option>
                  <option value="treasurer">Trésorier</option>
                  <option value="secretary">Secrétaire</option>
                  <option v-if="canDelete" value="admin_iut">Admin IUT</option>
                </select>
              </div>
              <div class="actions">
                <NcButton type="primary" class="btn-orange" @click.stop="saveMemberRole(member)" icon="icon-checkmark">OK</NcButton>
                <NcButton type="tertiary" class="btn-cancel" @click.stop="cancelEditMember" icon="icon-close">Annuler</NcButton>
              </div>
            </div>

            <NcActions :primary="true" menu-name="Actions" v-if="editingMemberId !== member.user_id">
              <NcActionButton 
                v-if="!(member.user_id === currentUserId && member.role === 'president')"
                @click="startEditMember(member)" 
                icon="icon-rename" 
                :close-after-click="true"
              >
                {{ t('dtcassociations', 'Modifier Rôle') }}
              </NcActionButton>
              <NcActionButton v-if="!(member.user_id === currentUserId && member.role === 'president')" @click="openRemoveMemberModal(member)" icon="icon-delete" :close-after-click="true">
                {{ t('dtcassociations', 'Retirer') }}
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
          <p><strong>Attention :</strong> Vous êtes sur le point de supprimer l'association <em>{{ associationToDelete?.name }}</em>.</p>
          <p class="warning-text">Cette action est irréversible. Le dossier de groupe et toutes les données seront supprimés.</p>
        </div>
          <div class="modal-footer-custom">
            <NcButton @click="closeDeleteModal">Annuler</NcButton>
            <NcButton @click="confirmDeleteAssociation" type="error">Confirmer la suppression</NcButton>
          </div>
      </NcModal>

      <NcModal v-if="showRenameModal" @close="closeRenameModal" title="Renommer l'association" size="small">
        <div class="modal-content">
          <p>Entrez le nouveau nom pour l'association :</p>
          <input v-model="renameInput" type="text" class="dtc-input full-width" @keyup.enter="confirmRenameAssociation" ref="renameInput" />
          <p class="info-text">Le dossier d'équipe sera également renommé.</p>
        </div>
          <div class="modal-footer-custom">
            <NcButton @click="closeRenameModal">Annuler</NcButton>
            <NcButton @click="confirmRenameAssociation" type="primary" class="btn-orange">Valider</NcButton>
          </div>
      </NcModal>

      <NcModal v-if="showRemoveMemberModal" @close="closeRemoveMemberModal" title="Retirer un membre" size="small">
        <div class="modal-content">
          <p>Voulez-vous vraiment retirer <strong>{{ memberToRemove?.user_id }}</strong> de cette association ?</p>
          <p class="warning-text">Il perdra l'accès au dossier d'équipe.</p>
        </div>
          <div class="modal-footer-custom">
            <NcButton @click="closeRemoveMemberModal">Annuler</NcButton>
            <NcButton @click="confirmRemoveMember" type="error">Retirer</NcButton>
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
      loading: false,
      selectedAssociation: null,
      members: [],
      membersLoading: false,
      selectedUser: null,
      userOptions: [],
      isLoadingUsers: false,
      newMemberRole: 'member',

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
      currentUserId: ''
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
    } catch(e) {}
  },
  methods: {
    async checkPermissions() {
      try {
        const response = await axios.get(generateUrl('/apps/dtcassociations/api/1.0/user/permissions'));
        this.canDelete = response.data.canDelete;
      } catch (e) { this.canDelete = false; }
    },
    async fetchAssociations() {
      this.loading = true;
      try {
        const response = await axios.get(generateUrl('/apps/dtcassociations/api/1.0/associations'));
        this.associations = response.data;
      } catch (e) { console.error(e); } finally { this.loading = false; }
    },
    async createAssociation() {
      if (!this.newAssocName.trim()) return;
      this.loading = true;
      try {
        const code = this.newAssocName.toLowerCase().replace(/[^a-z0-9]/g, '');
        await axios.post(generateUrl('/apps/dtcassociations/api/1.0/associations'), { name: this.newAssocName, code: code });
        this.newAssocName = '';
        await this.fetchAssociations();
      } catch (e) { alert(t('dtcassociations', 'Erreur création')); } finally { this.loading = false; }
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
      } catch (e) { alert(t('dtcassociations', 'Erreur suppression : vous n\'avez pas les droits.')); } finally { this.loading = false; }
    },
    openRenameModal(assoc) {
      this.associationToRename = assoc;
      this.renameInput = assoc.name;
      this.showRenameModal = true;
      this.$nextTick(() => { if(this.$refs.renameInput) this.$refs.renameInput.focus(); });
    },
    closeRenameModal() {
      this.showRenameModal = false;
      this.associationToRename = null;
      this.renameInput = '';
    },
    async confirmRenameAssociation() {
      if (!this.associationToRename || !this.renameInput.trim()) return;
      const id = this.associationToRename.id;
      const newName = this.renameInput;
      this.showRenameModal = false;
      this.loading = true;
      try {
        await axios.put(generateUrl(`/apps/dtcassociations/api/1.0/associations/${id}`), { name: newName });
        await this.fetchAssociations();
        if (this.selectedAssociation?.id === id) this.selectedAssociation.name = newName;
      } catch (e) { alert(t('dtcassociations', 'Erreur modification')); } finally { this.loading = false; }
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
      await this.updateMemberRoleCall(this.selectedUser.id, this.newMemberRole);
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
        await this.updateMemberRoleCall(member.user_id, this.editingMemberRole);
      }
      this.cancelEditMember();
    },
    async updateMemberRoleCall(userId, role) {
      this.membersLoading = true;
      try {
        await axios.post(generateUrl(`/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members`), {
          userId: userId, role: role
        });
        await this.fetchMembers();
      } catch (e) { alert(t('dtcassociations', "Erreur sauvegarde")); } finally { this.membersLoading = false; }
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
      } catch (e) { alert(t('dtcassociations', 'Erreur suppression')); } finally { this.membersLoading = false; }
    },
    translateRole(role) {
      const roles = { 'member': 'Membre', 'president': 'Président', 'treasurer': 'Trésorier', 'secretary': 'Secrétaire', 'admin_iut': 'Admin IUT' };
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
.app-content{ background: var(--color-dtc-primary) !important;}
.app-dtcassociations, .app-dtcassociations * {
  font-family: 'Luciole', sans-serif !important;
}
.button-vue--vue-primary{
  background-color: var(--color-dtc-secondary) !important;
  color: var(--color-dtc-text) !important;
}

.modal-container{
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
.app-dtcassociations { margin: 0;}
.dtc-container { padding: 20px; }
.app-title { color: var(--color-dtc-text); font-weight: bold; margin-bottom: 20px; }
.header-actions { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
.add-form { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
.dtc-input { flex-grow: 1; padding: 10px; border: 1px solid var(--color-border); border-radius: var(--border-radius); box-sizing: border-box; }
.dtc-input.full-width { width: 100%; margin: 10px 0; }
.user-select-container { flex-grow: 1; min-width: 200px; }
.dtc-select { padding: 10px; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-text-main); height: 44px; }
.dtc-select-small { padding: 5px; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-text-main); margin-right: 5px; }
.association-list { list-style: none; padding: 0; }
.association-item {
    display: flex; 
    align-items: center; 
    gap: 10px; 
    padding: 15px; 
    color: var(--color-dtc-text);
    border-radius: var(--border-radius-large);
    background: rgba(0, 0, 0, 0.4);
    margin-bottom: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.association-item.clickable { cursor: pointer; }
.association-item.clickable:hover { opacity: 0.95; }
.association-item .icon { font-size: 24px; opacity: 1; color: var(--color-dtc-text); }
.association-item .icon-white { filter: brightness(0) invert(1); cursor: pointer;}
.association-item .info { flex-grow: 1; display: flex; align-items: center; gap: 10px; cursor: pointer; }
.association-item .info.edit-mode { gap: 5px; justify-content: space-between;}
.user { display: flex; align-items: center; gap: 10px; }
.actions { display: flex; gap: 5px; }
.role-badge { 
    background-color: rgba(255,255,255,0.2); 
    color: #fff; 
    padding: 4px 10px; 
    border-radius: 12px; 
    font-size: 0.85em; 
    font-weight: bold; 
    border: 1px solid rgba(255,255,255,0.3);
}
.modal-content { padding: 20px; }
.modal-footer-custom { 
    margin-top: 20px;
    display: flex; 
    justify-content: flex-end; 
    gap: 10px; 
}
.warning-text { color: var(--color-dtc-secondary); margin-top: 10px; }
.info-text { color: var(--color-text-maxcontrast); font-size: 0.9em; font-style: italic; }
::v-deep .btn-orange {
    background-color: var(--color-dtc-secondary) !important;
    border-color: var(--color-dtc-secondary) !important;
    color: #fff !important;
}
::v-deep .btn-orange:hover, ::v-deep .btn-orange:focus {
    background-color: #b83a15 !important;
    border-color: #b83a15 !important;
}
</style>