import NcContent from "@nextcloud/vue/dist/Components/NcContent";
import NcAppNavigation from "@nextcloud/vue/dist/Components/NcAppNavigation";
import NcAppNavigationItem from "@nextcloud/vue/dist/Components/NcAppNavigationItem";
import NcAppContent from "@nextcloud/vue/dist/Components/NcAppContent";
import NcButton from "@nextcloud/vue/dist/Components/NcButton";
import NcActions from "@nextcloud/vue/dist/Components/NcActions";
import NcActionButton from "@nextcloud/vue/dist/Components/NcActionButton";
import NcMultiselect from "@nextcloud/vue/dist/Components/NcMultiselect";
import NcModal from "@nextcloud/vue/dist/Components/NcModal";
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

export default {
  name: "App",
  components: {
    NcContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppContent,
    NcButton,
    NcActions,
    NcActionButton,
    NcMultiselect,
    NcModal,
  },
  data() {
    return {
      associations: [],
      newAssocName: "",
      creationError: "",
      renameError: "",
      loading: false,
      selectedAssociation: null,
      members: [],
      membersLoading: false,
      selectedUser: null,
      userOptions: [],
      isLoadingUsers: false,
      newMemberRole: "president",

      showDeleteModal: false,
      associationToDelete: null,
      showRenameModal: false,
      associationToRename: null,
      renameInput: "",
      showRemoveMemberModal: false,
      memberToRemove: null,
      editingMemberId: null,
      editingMemberRole: "member",

      isAdmin: false,
      currentUserId: "",
      canDelete: false,
      canManage: false,

      notification: {
        message: "",
        type: "error",
      },
      notificationTimeout: null,
    };
  },

  async mounted() {
    if (window.OC && window.OC.getCurrentUser) {
      this.currentUserId = window.OC.getCurrentUser().uid;
    }

    window.addEventListener("popstate", this.handlePopState);

    await this.checkPermissions();
    await this.fetchAssociations();

    const urlParts = window.location.pathname.split("/");
    let lastPart = urlParts[urlParts.length - 1];

    if (lastPart === "" && urlParts.length > 1) {
      lastPart = urlParts[urlParts.length - 2];
    }

    if (lastPart && lastPart !== "dtcassociations") {
      const decodedName = decodeURIComponent(lastPart);

      const found =
        this.associations.find((a) => a.code === lastPart) ||
        this.associations.find((a) => a.name === decodedName) ||
        this.associations.find((a) => this.slugify(a.name) === lastPart) ||
        (!isNaN(lastPart)
          ? this.associations.find((a) => a.id === parseInt(lastPart))
          : null);

      if (found) {
        this.selectAssociation(found, false);
      }
    }

    try {
      if (window.OC && window.OC.isUserAdmin)
        this.isAdmin = window.OC.isUserAdmin();
    } catch (e) {}
  },

  beforeDestroy() {
    window.removeEventListener("popstate", this.handlePopState);
  },

  methods: {
    slugify(text) {
      return text
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "-")
        .replace(/\s+/g, "-")
        .replace(/[^\w_]+/g, "-")
        .replace(/__+/g, "-")
        .replace(/^_+/, "-")
        .replace(/_+$/, "-");
    },

    handlePopState(event) {
      if (event.state && event.state.assocId) {
        const found = this.associations.find(
          (a) => a.id === event.state.assocId,
        );
        if (found) {
          this.selectedAssociation = found;
          this.fetchMembers();
        }
      } else {
        this.selectedAssociation = null;
      }
    },

    showNotification(message, type = "error") {
      if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
      this.notification = { message, type };
      this.notificationTimeout = setTimeout(() => {
        this.closeNotification();
      }, 5000);
    },
    closeNotification() {
      this.notification.message = "";
      if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
    },

    async checkPermissions() {
      try {
        const response = await axios.get(
          generateUrl("/apps/dtcassociations/api/1.0/user/permissions"),
        );
        this.canDelete = response.data.canDelete;
        this.canManage = response.data.canManage;
      } catch (e) {
        this.canDelete = false;
        this.canManage = false;
      }
    },
    async fetchAssociations() {
      this.loading = true;
      try {
        const response = await axios.get(
          generateUrl("/apps/dtcassociations/api/1.0/associations"),
        );
        this.associations = response.data;
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },
    async createAssociation() {
      this.creationError = "";
      if (!this.newAssocName.trim()) return;

      const forbiddenPattern = /[^\p{L}0-9 _'-]/u;
      if (forbiddenPattern.test(this.newAssocName)) {
        this.creationError = t(
          "dtcassociations",
          "Seuls les lettres, chiffres, tirets, tirets du bas, apostrophes et espaces sont autorisés.",
        );
        return;
      }

      this.loading = true;
      try {
        const code = this.slugify(this.newAssocName);

        await axios.post(
          generateUrl("/apps/dtcassociations/api/1.0/associations"),
          { name: this.newAssocName, code: code },
        );
        this.newAssocName = "";
        await this.fetchAssociations();
        this.showNotification(
          t("dtcassociations", "Association créée avec succès"),
          "success",
        );
      } catch (e) {
        console.error(e);
        this.creationError = t(
          "dtcassociations",
          "Erreur lors de la création (nom déjà pris ?)",
        );
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
        await axios.delete(
          generateUrl(`/apps/dtcassociations/api/1.0/associations/${id}`),
        );
        if (this.selectedAssociation?.id === id)
          this.selectedAssociation = null;
        await this.fetchAssociations();
        this.showNotification(
          t("dtcassociations", "Association supprimée avec succès"),
          "success",
        );
      } catch {
        this.showNotification(
          t(
            "dtcassociations",
            "Erreur lors de la suppression de l'association",
          ),
          "error",
        );
      } finally {
        this.loading = false;
      }
    },
    openRenameModal(assoc) {
      this.associationToRename = assoc;
      this.renameInput = assoc.name;
      this.renameError = "";
      this.showRenameModal = true;
      this.$nextTick(() => {
        if (this.$refs.renameInput) this.$refs.renameInput.focus();
      });
    },
    closeRenameModal() {
      this.showRenameModal = false;
      this.associationToRename = null;
      this.renameInput = "";
    },
    async confirmRenameAssociation() {
      this.renameError = "";
      if (!this.associationToRename || !this.renameInput.trim()) return;

      const forbiddenPattern = /[^\p{L}0-9 _'-]/u;
      if (forbiddenPattern.test(this.renameInput)) {
        this.renameError = t(
          "dtcassociations",
          "Seuls les lettres, chiffres, tirets, tirets du bas, apostrophes et espaces sont autorisés.",
        );
        return;
      }

      const id = this.associationToRename.id;
      const newName = this.renameInput;
      this.showRenameModal = false;
      this.loading = true;
      try {
        await axios.put(
          generateUrl(`/apps/dtcassociations/api/1.0/associations/${id}`),
          { name: newName },
        );
        await this.fetchAssociations();
        if (this.selectedAssociation?.id === id) {
          this.selectedAssociation.name = newName;

          const newUrl = generateUrl(
            "/apps/dtcassociations/" + this.slugify(newName),
          );
          window.history.replaceState({ assocId: id }, "", newUrl);
        }
        this.showNotification(
          t("dtcassociations", "Association renommée avec succès"),
          "success",
        );
      } catch (e) {
        console.error(e);
        this.renameError = t(
          "dtcassociations",
          "Erreur : ce nom est peut-être déjà utilisé ou invalide.",
        );
      } finally {
        this.loading = false;
      }
    },

    selectAssociation(assoc, pushState = true) {
      this.selectedAssociation = assoc;
      this.fetchMembers();

      if (pushState) {
        const newUrl = generateUrl(
          "/apps/dtcassociations/" + this.slugify(assoc.name),
        );
        window.history.pushState({ assocId: assoc.id }, "", newUrl);
      }
    },

    deselectAssociation() {
      this.selectedAssociation = null;
      const newUrl = generateUrl("/apps/dtcassociations/");
      window.history.pushState({}, "", newUrl);
    },
    async fetchMembers() {
      if (!this.selectedAssociation) return;
      this.membersLoading = true;
      try {
        const response = await axios.get(
          generateUrl(
            `/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members`,
          ),
        );
        this.members = response.data;
      } catch (e) {
        console.error(e);
      } finally {
        this.membersLoading = false;
      }
    },
    async searchUsers(query) {
      if (!query || query.length < 2) return;
      this.isLoadingUsers = true;
      try {
        const url =
          window.OC.linkToOCS("apps/files_sharing/api/v1", 2) + "sharees";
        const response = await axios.get(url, {
          params: {
            search: query,
            itemType: "file",
            format: "json",
            perPage: 20,
          },
        });
        const users = response.data.ocs?.data?.users || [];
        this.userOptions = users.map((u) => ({
          id: u.value.shareWith,
          label: u.label,
        }));
      } catch (e) {
        console.error(e);
      } finally {
        this.isLoadingUsers = false;
      }
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
      const isAdd = !this.members.some((m) => m.user_id === userId);

      try {
        await axios.post(
          generateUrl(
            `/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members`,
          ),
          {
            userId: userId,
            role: role,
          },
        );
        await this.fetchMembers();
        this.showNotification(
          isAdd
            ? t("dtcassociations", "Membre ajouté avec succès")
            : t("dtcassociations", "Rôle mis à jour"),
          "success",
        );
      } catch (e) {
        const errorMessage =
          e.response?.data?.error || e.response?.data?.message;

        if (errorMessage) {
          this.showNotification(errorMessage, "error");
        } else {
          this.showNotification(
            t("dtcassociations", "Erreur lors de la sauvegarde"),
            "error",
          );
        }
      } finally {
        this.membersLoading = false;
      }
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
        await axios.delete(
          generateUrl(
            `/apps/dtcassociations/api/1.0/associations/${this.selectedAssociation.id}/members/${userId}`,
          ),
        );
        await this.fetchMembers();
        this.showNotification(
          t("dtcassociations", "Membre retiré avec succès"),
          "success",
        );
      } catch {
        this.showNotification(
          t("dtcassociations", "Erreur lors de la suppression du membre"),
          "error",
        );
      } finally {
        this.membersLoading = false;
      }
    },
    formatSize(bytes) {
      if (bytes === undefined || bytes === null) return "0 B";
      if (bytes === 0) return "0 B";
      const k = 1024;
      const sizes = ["B", "KB", "MB", "GB", "TB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
    },
    calculatePercentage(usage, quota) {
      if (quota < 0) return 0;
      if (!quota || quota === 0) return 100;
      let percent = (usage / quota) * 100;
      return Math.min(percent, 100).toFixed(1);
    },
    formatQuota(quota) {
      if (quota < 0) return t("dtcassociations", "Illimité");
      return this.formatSize(quota);
    },
    translateRole(role) {
      const roles = {
        president: "Président / Vice-Président",
        treasurer: "Trésorier / Vice-Trésorier",
        secretary: "Secrétaire / Vice-Secrétaire",
        teacher: "Enseignant",
        admin_iut: "Admin IUT",
        invite: "Invité",
      };
      return roles[role] || role;
    },
  },
};
