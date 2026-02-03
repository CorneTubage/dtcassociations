<template>
  <NcContent app-name="dtcassociations" class="app-dtcassociations">
    <NcAppNavigation slot="navigation">
      <NcAppNavigationItem
        id="associations"
        name="Associations"
        :title="t('dtcassociations', 'Associations')"
        icon="icon-category-organization"
        :active="!selectedAssociation"
        @click="deselectAssociation" 
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
           <NcButton @click="deselectAssociation" type="tertiary" icon="icon-arrow-left-active">
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

<script src="./App.js"></script>
<style src="./App.scss"></style>