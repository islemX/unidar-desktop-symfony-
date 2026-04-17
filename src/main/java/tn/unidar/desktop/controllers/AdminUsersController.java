package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;

import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.ResourceBundle;

public class AdminUsersController implements Initializable {

    @FXML private VBox         loadingPane, emptyPane, usersContainer;
    @FXML private Label        lblCount, lblUsersTitle, lblUsersLoading, lblUsersEmptyTitle;
    @FXML private TextField    txtSearch;
    @FXML private ComboBox<String> comboRole;
    @FXML private Button       btnBack, btnSearch, btnClear;

    private List<JSONObject> allUsers = new ArrayList<>();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        translateUI();
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        comboRole.getItems().addAll(I18n.t("admin.users.all_roles"), "student", "owner", "admin");
        comboRole.setValue(I18n.t("admin.users.all_roles"));
        loadUsers();
    }

    private void translateUI() {
        if (lblUsersTitle != null) lblUsersTitle.setText("👥  " + I18n.t("admin.users.title"));
        if (btnBack != null) btnBack.setText("← " + I18n.t("admin.back_dashboard"));
        if (txtSearch != null) txtSearch.setPromptText("🔍  " + I18n.t("admin.users.search_prompt"));
        if (comboRole != null) comboRole.setPromptText(I18n.t("admin.users.all_roles"));
        if (btnSearch != null) btnSearch.setText(I18n.t("common.search"));
        if (btnClear != null) btnClear.setText(I18n.t("common.clear"));
        if (lblUsersLoading != null) lblUsersLoading.setText(I18n.t("admin.users.loading"));
        if (lblUsersEmptyTitle != null) lblUsersEmptyTitle.setText(I18n.t("admin.users.none"));
    }

    private void loadUsers() {
        show(loadingPane, true);
        show(emptyPane, false);
        usersContainer.getChildren().clear();

        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/admin.php?action=users");
                JSONArray users = resp.optJSONArray("users");
                allUsers.clear();
                if (users != null) {
                    for (int i = 0; i < users.length(); i++) allUsers.add(users.getJSONObject(i));
                }
                Platform.runLater(() -> renderUsers(allUsers));
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    show(loadingPane, false);
                    lblCount.setText(I18n.t("common.error"));
                });
            }
        }).start();
    }

    @FXML
    private void handleSearch() {
        String query = txtSearch.getText().trim().toLowerCase();
        String role  = comboRole.getValue();
        List<JSONObject> filtered = new ArrayList<>();
        for (JSONObject u : allUsers) {
            boolean matchesQuery = query.isEmpty()
                    || u.optString("full_name", "").toLowerCase().contains(query)
                    || u.optString("email", "").toLowerCase().contains(query);
            boolean matchesRole = role == null || role.equals(I18n.t("admin.users.all_roles"))
                    || role.equalsIgnoreCase(u.optString("role", ""));
            if (matchesQuery && matchesRole) filtered.add(u);
        }
        renderUsers(filtered);
    }

    @FXML
    private void clearSearch() {
        txtSearch.clear();
        comboRole.setValue(I18n.t("admin.users.all_roles"));
        renderUsers(allUsers);
    }

    private void renderUsers(List<JSONObject> users) {
        show(loadingPane, false);
        usersContainer.getChildren().clear();

        if (users.isEmpty()) {
            show(emptyPane, true);
            lblCount.setText("0 " + I18n.t("admin.stats.users"));
            return;
        }
        show(emptyPane, false);
        lblCount.setText(users.size() + " " + I18n.t("admin.stats.users"));

        // Header row
        HBox header = buildHeaderRow();
        usersContainer.getChildren().add(header);

        for (JSONObject u : users) {
            usersContainer.getChildren().add(buildUserRow(u));
        }
    }

    private HBox buildHeaderRow() {
        HBox row = new HBox(0);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-background-color: #f1f5f9; -fx-padding: 10 16; -fx-background-radius: 8 8 0 0;");
        String[] cols = {
            I18n.t("admin.users.name"), I18n.t("admin.users.email"), I18n.t("admin.users.role"),
            I18n.t("admin.users.verified"), I18n.t("admin.users.joined"), I18n.t("admin.users.actions")
        };
        double[] widths = {200, 240, 100, 90, 110, 140};
        for (int i = 0; i < cols.length; i++) {
            Label lbl = new Label(cols[i]);
            lbl.setPrefWidth(widths[i]);
            lbl.setStyle("-fx-font-weight: bold; -fx-text-fill: #64748b; -fx-font-size: 12px;");
            row.getChildren().add(lbl);
        }
        return row;
    }

    private HBox buildUserRow(JSONObject u) {
        String name     = u.optString("full_name", "Unknown");
        String email    = u.optString("email", "");
        String role     = u.optString("role", "student");
        boolean verified= u.optBoolean("is_verified", false);
        String joined   = u.optString("created_at", "").replace("T", " ").replaceAll("\\..*", "");

        HBox row = new HBox(0);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-background-color: white; -fx-padding: 14 16;" +
                     "-fx-border-color: transparent transparent #f1f5f9 transparent;" +
                     "-fx-border-width: 0 0 1 0;");
        row.getStyleClass().add("admin-row");

        // Avatar + name
        String initial = name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase();
        Label avatar = new Label(initial);
        avatar.setStyle("-fx-background-color: #4f46e5; -fx-background-radius: 16;" +
                        " -fx-text-fill: white; -fx-font-weight: bold;" +
                        " -fx-min-width: 32; -fx-min-height: 32;" +
                        " -fx-max-width: 32; -fx-max-height: 32; -fx-alignment: CENTER;");

        Label lName = new Label(name);
        lName.setPrefWidth(160);
        lName.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");

        HBox nameCell = new HBox(8, avatar, lName);
        nameCell.setAlignment(Pos.CENTER_LEFT);
        nameCell.setPrefWidth(200);

        Label lEmail = new Label(email);
        lEmail.setPrefWidth(240);
        lEmail.setStyle("-fx-text-fill: #334155; -fx-font-size: 12px;");

        String roleColor = "owner".equals(role) ? "#d97706" : "admin".equals(role) ? "#dc2626" : "#4f46e5";
        Label lRole = new Label(capitalize(role));
        lRole.setPrefWidth(100);
        lRole.setStyle("-fx-background-color: " + roleColor + "22; -fx-text-fill: " + roleColor + ";" +
                       " -fx-font-weight: bold; -fx-font-size: 11px; -fx-background-radius: 8; -fx-padding: 2 8;");

        String verifText = verified ? I18n.t("admin.users.yes") : I18n.t("admin.users.no");
        Label lVerif = new Label((verified ? "✅ " : "⏳ ") + verifText);
        lVerif.setPrefWidth(90);
        lVerif.setStyle("-fx-text-fill: " + (verified ? "#16a34a" : "#64748b") + "; -fx-font-size: 12px;");

        Label lJoined = new Label(joined.isEmpty() ? "—" : joined.substring(0, Math.min(10, joined.length())));
        lJoined.setPrefWidth(110);
        lJoined.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");

        HBox actions = new HBox(8);
        actions.setPrefWidth(140);
        actions.setAlignment(Pos.CENTER_LEFT);

        boolean isBanned = "banned".equalsIgnoreCase(u.optString("status", "active"));
        String banText = isBanned ? I18n.t("admin.users.unban") : I18n.t("admin.users.ban");
        Button btnBan = new Button(banText);
        btnBan.getStyleClass().add(isBanned ? "btn-success" : "btn-danger");
        btnBan.getStyleClass().add("btn-sm");
        btnBan.setOnAction(e -> handleUpdateStatus(u, isBanned ? "active" : "banned"));

        Button btnDel = new Button("🗑️");
        btnDel.getStyleClass().addAll("btn-ghost", "btn-sm");
        btnDel.setStyle("-fx-text-fill: #ef4444;");
        btnDel.setOnAction(e -> handleDeleteUser(u));

        actions.getChildren().addAll(btnBan, btnDel);

        row.getChildren().addAll(nameCell, lEmail, lRole, lVerif, lJoined, actions);
        return row;
    }

    @FXML private void goBack() { NavigationService.getInstance().navigateTo("AdminDashboard.fxml"); }
    @FXML private void refresh() { loadUsers(); }

    private void handleUpdateStatus(JSONObject user, String newStatus) {
        String name = user.optString("full_name", "this user");
        String action = newStatus.equals("banned") ? I18n.t("admin.users.ban") : I18n.t("admin.users.unban");
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION,
                String.format(I18n.t("admin.users.confirm_ban"), action, name),
                ButtonType.YES, ButtonType.NO);
        alert.setHeaderText(null);
        alert.showAndWait().ifPresent(res -> {
            if (res == ButtonType.YES) {
                new Thread(() -> {
                    try {
                        JSONObject body = new JSONObject().put("status", newStatus);
                        if (newStatus.equals("banned")) body.put("notes", "Locked by Administrator");
                        
                        ApiClient.getInstance().put("/admin.php?user_id=" + user.getInt("id"), body);
                        Platform.runLater(() -> {
                            loadUsers(); // Refresh
                        });
                    } catch (Exception e) {
                        e.printStackTrace();
                        Platform.runLater(() -> {
                            Alert err = new Alert(Alert.AlertType.ERROR, "Failed to update user: " + e.getMessage());
                            err.show();
                        });
                    }
                }).start();
            }
        });
    }

    private void handleDeleteUser(JSONObject user) {
        String name = user.optString("full_name", "this user");
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION,
                String.format(I18n.t("admin.users.confirm_delete"), name),
                ButtonType.YES, ButtonType.NO);
        alert.setHeaderText(I18n.t("admin.users.delete_title"));
        alert.showAndWait().ifPresent(res -> {
            if (res == ButtonType.YES) {
                new Thread(() -> {
                    try {
                        ApiClient.getInstance().delete("/admin.php?user_id=" + user.getInt("id"));
                        Platform.runLater(this::loadUsers);
                    } catch (Exception e) {
                        e.printStackTrace();
                        Platform.runLater(() -> {
                            new Alert(Alert.AlertType.ERROR, "Delete failed: " + e.getMessage()).show();
                        });
                    }
                }).start();
            }
        });
    }

    private void show(javafx.scene.Node n, boolean v) { n.setVisible(v); n.setManaged(v); }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }
}
