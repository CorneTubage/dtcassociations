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
      <!-- VUE LISTE DES ASSOCIATIONS -->
      <div v-if="!selectedAssociation" class="dtc-container">
        <h2>{{ t('dtcassociations', 'Gestion des Associations') }}</h2>

        <div class="add-form">
          <input
            v-model="newAssocName"
            type="text"
            class="dtc-input"
            :placeholder="t('dtcassociations', 'Nom de la nouvelle association...')"
            @keyup.enter="createAssociation"
          />
          <NcButton type="primary" @click="createAssociation" :disabled="loading">
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
            <span class="icon-category-organization icon"></span>
            <div class="info">
              <span class="name">{{ assoc.name }}</span>
              <small class="code">({{ assoc.code }})</small>
            </div>
            
            <NcActions :primary="true" menu-name="Actions" @click.stop>
              <NcActionButton @click.stop="editAssociation(assoc)" icon="icon-rename" :close-after-click="true">
                {{ t('dtcassociations', 'Renommer') }}
              </NcActionButton>
              <NcActionButton @click.stop="deleteAssociation(assoc.id)" icon="icon-delete" :close-after-click="true">
                {{ t('dtcassociations', 'Supprimer') }}
              </NcActionButton>
            </NcActions>
          </li>
          <li v-if="associations.length === 0" class="empty-state">
            {{ t('dtcassociations', 'Aucune association trouvée.') }}
          </li>
        </ul>
      </div>

      <!-- VUE DÉTAIL / MEMBRES -->
      <div v-else class="dtc-container">
        <div class="header-actions">
           <NcButton @click="selectedAssociation = null" type="tertiary" icon="icon-arrow-left-active">
             {{ t('dtcassociations', 'Retour') }}
           </NcButton>
           <h2>{{ selectedAssociation.name }} - Membres</h2>
        </div>

        <div class="add-form">
          <!-- RECHERCHE UTILISATEUR CORRIGÉE -->
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
          </select>
          <NcButton type="primary" @click="addMember" :disabled="membersLoading || !selectedUser">
            {{ t('dtcassociations', 'Ajouter') }}
          </NcButton>
        </div>

        <div v-if="membersLoading" class="icon-loading"></div>

        <ul v-else class="association-list">
          <li v-for="member in members" :key="member.id" class="association-item">
            <span class="icon-user icon"></span>
            <div class="info">
              <span class="name">{{ member.user_id }}</span>
              <span class="role-badge">{{ translateRole(member.role) }}</span>
            </div>
            <NcActions :primary="true" menu-name="Actions">
              <NcActionButton @click="removeMember(member.user_id)" icon="icon-delete" :close-after-click="true">
                {{ t('dtcassociations', 'Retirer') }}
              </NcActionButton>
            </NcActions>
          </li>
          <li v-if="members.length === 0" class="empty-state">
            {{ t('dtcassociations', 'Aucun membre dans cette association.') }}
          </li>
        </ul>
      </div>
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
import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router';

export default {
  name: 'App',
  components: {
    NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent, 
    NcButton, NcActions, NcActionButton, NcMultiselect
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
    };
  },
  mounted() {
    this.fetchAssociations();
  },
  methods: {
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
    async deleteAssociation(id) {
      if (!confirm(t('dtcassociations', 'Supprimer ?'))) return;
      this.loading = true;
      try {
        await axios.delete(generateUrl(`/apps/dtcassociations/api/1.0/associations/${id}`));
        if (this.selectedAssociation?.id === id) this.selectedAssociation = null;
        await this.fetchAssociations();
      } catch (e) { alert(t('dtcassociations', 'Erreur suppression')); } finally { this.loading = false; }
    },
    async editAssociation(assoc) {
      const newName = prompt(t('dtcassociations', 'Nouveau nom :'), assoc.name);
      if (!newName || newName === assoc.name) return;
      try {
        await axios.put(generateUrl(`/apps/dtcassociations/api/1.0/associations/${assoc.id}`), { name: newName });
        await this.fetchAssociations();
      } catch (e) { alert(t('dtcassociations', 'Erreur modification')); }
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
    
    // --- NOUVELLE MÉTHODE DE RECHERCHE CORRIGÉE ---
    async searchUsers(query) {
      if (!query || query.length < 2) return;
      this.isLoadingUsers = true;
      try {
        // CORRECTION 404 :
        // linkToOCS('.../v1/sharees') ajoute un slash final -> '.../sharees/' (Erreur 404)
        // linkToOCS('.../v1') ajoute un slash final -> '.../v1/'
        // On construit donc l'URL en deux parties : la base + 'sharees' sans slash.
        const baseUrl = window.OC.linkToOCS('apps/files_sharing/api/v1', 2);
        const url = baseUrl + 'sharees';
        
        const response = await axios.get(url, {
          params: { search: query, itemType: 'file', format: 'json', perPage: 20 }
        });
        
        const users = response.data.ocs?.data?.users || [];
        // Mapping pour NcMultiselect
        this.userOptions = users.map(u => ({
          id: u.value.shareWith,
          label: u.label
        }));
      } catch (e) {
        console.error("Erreur recherche", e);
      } finally {
        this.isLoadingUsers = false;
      }
    },

    async addMember() {
      if (!this.selectedUser) return;
      this.membersLoading = true;
      try {
        await axios.post(generateUrl(`/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members`), {
          userId: this.selectedUser.id,
          role: this.newMemberRole
        });
        this.selectedUser = null;
        await this.fetchMembers();
      } catch (e) {
        alert(t('dtcassociations', "Erreur ajout membre"));
      } finally {
        this.membersLoading = false;
      }
    },
    async removeMember(userId) {
      if (!confirm(t('dtcassociations', 'Retirer ?'))) return;
      this.membersLoading = true;
      try {
        await axios.delete(generateUrl(`/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members/${userId}`));
        await this.fetchMembers();
      } catch (e) { alert(t('dtcassociations', 'Erreur suppression')); } finally { this.membersLoading = false; }
    },
    translateRole(role) {
      const roles = { 'member': 'Membre', 'president': 'Président', 'treasurer': 'Trésorier', 'secretary': 'Secrétaire' };
      return roles[role] || role;
    }
  }
};
</script>

<style scoped>
.dtc-container { 
  padding: 20px;
  max-width: 800px;
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
  margin-bottom: 20px;
  align-items: flex-start;
}
.dtc-input {
  flex-grow: 1;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
}
.user-select-container {
  flex-grow: 1;
  min-width: 200px;
}
.dtc-select {
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
  color: var(--color-text-main);
  height: 44px;
}
.association-list {
  list-style: none;
  padding: 0;
}
.association-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-bottom: 1px solid var(--color-border);
}
.association-item.clickable {
  cursor: pointer;
}
.association-item.clickable:hover {
  background-color: var(--color-background-hover);
}
.association-item .icon {
  font-size: 20px;
  opacity: 0.7;
}
.association-item .info {
  flex-grow: 1;
  display: flex;
  align-items: baseline;
  gap: 10px;
}
.association-item .code {
  color: var(--color-text-maxcontrast);
}
.role-badge {
  background-color: var(--color-primary-light);
  color: var(--color-primary);
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 0.85em;
  font-weight: bold;
}
</style>