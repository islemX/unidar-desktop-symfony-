package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.utils.I18n;
import tn.unidar.desktop.utils.ContractViewer;
import javafx.scene.control.Alert;
import javafx.scene.control.ButtonType;
import javafx.scene.control.Tooltip;

import java.net.URL;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ResourceBundle;

public class AdminDashboardController implements Initializable {

    @FXML private Label lblDateTime;
    @FXML private Label lblTotalUsers, lblTotalListings;
    @FXML private Label lblPendingVerif, lblTotalContracts, lblPremiumUsers;
    @FXML private Label lblVerifBadge;
    @FXML private Label lblApiStatus, lblDbStatus;
    @FXML private Label lblApiStatusLabel, lblDbStatusLabel, lblVersionLabel;
    @FXML private Label lblRecentReportsTitle, lblTerminationRequestsTitle, lblRecentSubsTitle;
    @FXML private Button btnManageReports;
    @FXML private Button btnUsersQuick, btnListingsQuick, btnVerifsQuick, btnMsgsQuick, btnPaymentsQuick;
    @FXML private VBox  usersPreview, listingsPreview, verifPreview, contractsPreview;
    @FXML private VBox  reportsPreview, termsPreview, subsPreview;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        translateUI();
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        // Verify admin role
        String role = AuthService.getInstance().getCurrentUser().getRole();
        if (!"admin".equalsIgnoreCase(role)) {
            NavigationService.getInstance().navigateTo("Dashboard.fxml");
            return;
        }
        // Show current date/time
        lblDateTime.setText(LocalDateTime.now()
                .format(DateTimeFormatter.ofPattern("MMM dd, yyyy  HH:mm")));

        loadStats();
    }

    private void translateUI() {
        if (lblRecentReportsTitle != null) lblRecentReportsTitle.setText(I18n.t("admin.recent_reports"));
        if (btnManageReports != null) btnManageReports.setText(I18n.t("admin.manage_reports"));
        if (lblTerminationRequestsTitle != null) lblTerminationRequestsTitle.setText(I18n.t("admin.termination_requests"));
        if (lblRecentSubsTitle != null) lblRecentSubsTitle.setText(I18n.t("admin.recent_subs"));
        
        if (btnUsersQuick != null) btnUsersQuick.setText("👥\n" + I18n.t("admin.users_btn"));
        if (btnListingsQuick != null) btnListingsQuick.setText("🏢\n" + I18n.t("admin.listings_btn"));
        if (btnVerifsQuick != null) btnVerifsQuick.setText("🛡️\n" + I18n.t("admin.verifs_btn"));
        if (btnMsgsQuick != null) btnMsgsQuick.setText("💬\n" + I18n.t("admin.msgs_btn"));
        if (btnPaymentsQuick != null) btnPaymentsQuick.setText("💳\n" + I18n.t("admin.payments_btn"));
        
        if (lblApiStatusLabel != null) lblApiStatusLabel.setText(I18n.t("admin.api_status"));
        if (lblDbStatusLabel != null) lblDbStatusLabel.setText(I18n.t("admin.db_status"));
        if (lblVersionLabel != null) lblVersionLabel.setText(I18n.t("admin.version"));
        
        if (lblApiStatus != null) lblApiStatus.setText("✅ " + I18n.t("admin.online"));
        if (lblDbStatus != null) lblDbStatus.setText("✅ " + I18n.t("admin.online"));
    }

    @FXML
    private void gotoPaymentsAdmin() {
        NavigationService.getInstance().navigateTo("AdminPayments.fxml");
    }

    private void loadStats() {
        new Thread(() -> {
            try {
                // Stats (single endpoint: admin.php?action=stats)
                JSONObject statsResp = ApiClient.getInstance().get("/admin.php?action=stats");
                JSONObject stats = statsResp.optJSONObject("stats");
                if (stats == null) stats = statsResp; // fallback

                int userCount    = stats.optInt("total_users", 0);
                int listingCount = stats.optInt("active_listings", 0);  // API key is active_listings
                int verifCount   = stats.optInt("pending_verifications", 0);
                int contractCount= stats.optInt("total_contracts", 0);
                int subCount     = stats.optInt("active_subscriptions", 0);

                // Recent users (admin.php?action=users)
                JSONObject usersResp = ApiClient.getInstance().get("/admin.php?action=users");
                JSONArray users = usersResp.optJSONArray("users");

                // Recent listings (listings.php)
                JSONObject listingsResp = ApiClient.getInstance().get("/listings.php");
                JSONArray listings = listingsResp.optJSONArray("listings");

                // Pending verifications (verifications.php?status=pending)
                JSONObject verifResp = ApiClient.getInstance().get("/verifications.php?status=pending");
                JSONArray verifs = verifResp.optJSONArray("verifications");
                int pendingVerif = verifs != null ? verifs.length() : verifCount;

                // Recent Contracts
                JSONObject contractsResp = ApiClient.getInstance().get("/contracts.php?action=user-contracts");
                JSONArray contracts = contractsResp.optJSONArray("contracts");

                // Recent reports (reports.php?status=open)
                JSONObject reportsResp = ApiClient.getInstance().get("/reports.php?status=open");
                JSONArray reports = reportsResp.optJSONArray("reports");

                // Termination requests (contracts.php?action=get-termination-requests)
                JSONObject termsResp = ApiClient.getInstance().get("/contracts.php?action=get-termination-requests");
                JSONArray terms = termsResp.optJSONArray("requests");
                if (terms == null) terms = termsResp.optJSONArray("data"); // check alternative key

                // Recent subscriptions (subscriptions.php?all=1)
                JSONObject subsResp = ApiClient.getInstance().get("/subscriptions.php?all=1");
                JSONArray subs = subsResp.optJSONArray("subscriptions");

                final int fuserCount = userCount, flistingCount = listingCount;
                final int fverifCount = pendingVerif, fcontractCount = contractCount, fsubCount = subCount;
                final JSONArray finalUsers    = users;
                final JSONArray finalListings = listings;
                final JSONArray finalVerifs   = verifs;
                final JSONArray finalContracts= contracts;
                final JSONArray finalReports  = reports;
                final JSONArray finalTerms    = terms;
                final JSONArray finalSubs     = subs;

                Platform.runLater(() -> {
                    lblTotalUsers.setText(String.valueOf(fuserCount));
                    lblTotalListings.setText(String.valueOf(flistingCount));
                    lblPendingVerif.setText(String.valueOf(fverifCount));
                    lblTotalContracts.setText(String.valueOf(fcontractCount));
                    lblPremiumUsers.setText(String.valueOf(fsubCount));
                    lblVerifBadge.setText(String.valueOf(fverifCount));
                    lblApiStatus.setText("✅ Online");
                    lblApiStatus.setStyle("-fx-text-fill: #16a34a; -fx-font-weight: bold; -fx-font-size: 14px;");
                    lblDbStatus.setText("✅ Connected");
                    lblDbStatus.setStyle("-fx-text-fill: #16a34a; -fx-font-weight: bold; -fx-font-size: 14px;");

                    renderUsersPreview(finalUsers);
                    renderListingsPreview(finalListings);
                    renderVerifPreview(finalVerifs);
                    renderContractsPreview(finalContracts);
                    renderReportsPreview(finalReports);
                    renderTermsPreview(finalTerms);
                    renderSubsPreview(finalSubs);
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    lblTotalUsers.setText("—");
                    lblTotalListings.setText("—");
                    lblPendingVerif.setText("—");
                    lblTotalContracts.setText("—");
                    lblPremiumUsers.setText("—");
                    lblApiStatus.setText("⚠️ " + I18n.t("common.error"));
                    lblApiStatus.setStyle("-fx-text-fill: #dc2626; -fx-font-weight: bold; -fx-font-size: 14px;");
                });
            }
        }).start();
    }


    private void renderUsersPreview(JSONArray users) {
        usersPreview.getChildren().clear();
        if (users == null || users.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_users"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            usersPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(users.length(), 4); i++) {
            JSONObject u = users.getJSONObject(i);
            usersPreview.getChildren().add(buildUserRow(u));
        }
    }

    private HBox buildUserRow(JSONObject u) {
        String name  = u.optString("full_name", "Unknown");
        String email = u.optString("email", "");
        String role  = u.optString("role", "student");

        HBox row = new HBox(12);
        row.setAlignment(Pos.CENTER_LEFT);
        row.getStyleClass().add("admin-row");

        String initial = name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase();
        Label avatar = new Label(initial);
        avatar.setStyle("-fx-background-color: #4f46e5; -fx-background-radius: 16;" +
                        " -fx-text-fill: white; -fx-font-weight: bold;" +
                        " -fx-min-width: 32; -fx-min-height: 32;" +
                        " -fx-max-width: 32; -fx-max-height: 32; -fx-alignment: CENTER;");

        VBox info = new VBox(2);
        HBox.setHgrow(info, Priority.ALWAYS);
        Label lName  = new Label(name);
        lName.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
        Label lEmail = new Label(email);
        lEmail.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
        info.getChildren().addAll(lName, lEmail);

        String badgeColor = "owner".equals(role) ? "#d97706" : "admin".equals(role) ? "#dc2626" : "#4f46e5";
        String roleKey = "reg." + role; // uses reg.student, reg.owner, etc.
        Label roleBadge = new Label(I18n.t(roleKey));
        roleBadge.setStyle("-fx-background-color: " + badgeColor + "22; -fx-text-fill: " + badgeColor + ";" +
                           " -fx-font-weight: bold; -fx-font-size: 11px;" +
                           " -fx-background-radius: 8; -fx-padding: 2 8 2 8;");

        row.getChildren().addAll(avatar, info, roleBadge);
        return row;
    }

    private void renderListingsPreview(JSONArray listings) {
        listingsPreview.getChildren().clear();
        if (listings == null || listings.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_listings"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            listingsPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(listings.length(), 4); i++) {
            JSONObject l = listings.getJSONObject(i);
            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.getStyleClass().add("admin-row");

            Label icon = new Label("🏢");
            icon.setStyle("-fx-font-size: 18px;");

            VBox info = new VBox(2);
            HBox.setHgrow(info, Priority.ALWAYS);
            Label title = new Label(String.format(I18n.t("admin.listing_no"), l.optInt("id")));
            title.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label sub = new Label(l.optString("address", "") + "  ·  " + l.optDouble("price", 0) + " " + I18n.t("detail.price_month"));
            sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
            info.getChildren().addAll(title, sub);

            String status = l.optString("status", "active");
            String statusTxt = status.equalsIgnoreCase("active") ? I18n.t("admin.status.active") : I18n.t("admin.status.error");
            Label badge = new Label(statusTxt);
            badge.getStyleClass().addAll("badge", status.equalsIgnoreCase("active") ? "badge-success" : "badge-neutral");

            row.getChildren().addAll(icon, info, badge);
            listingsPreview.getChildren().add(row);
        }
    }

    private void renderVerifPreview(JSONArray verifs) {
        verifPreview.getChildren().clear();
        if (verifs == null || verifs.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_verifs"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            verifPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(verifs.length(), 3); i++) {
            JSONObject v = verifs.getJSONObject(i);
            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.getStyleClass().add("admin-row");

            Label icon = new Label("🛡️");
            icon.setStyle("-fx-font-size: 16px;");

            VBox info = new VBox(2);
            HBox.setHgrow(info, Priority.ALWAYS);
            Label name = new Label(v.optString("user_name", "User #" + v.optInt("user_id")));
            name.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label type = new Label(String.format(I18n.t("admin.id_type"), v.optString("id_type", I18n.t("common.unknown"))));
            type.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
            info.getChildren().addAll(name, type);

            Button reviewBtn = new Button(I18n.t("admin.review") + " →");
            reviewBtn.getStyleClass().addAll("btn-primary", "btn-sm");
            reviewBtn.setOnAction(e -> NavigationService.getInstance().navigateTo("AdminVerifications.fxml"));

            row.getChildren().addAll(icon, info, reviewBtn);
            verifPreview.getChildren().add(row);
        }
    }

    private void renderContractsPreview(JSONArray contracts) {
        contractsPreview.getChildren().clear();
        if (contracts == null || contracts.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_contracts"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            contractsPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(contracts.length(), 4); i++) {
            JSONObject c = contracts.getJSONObject(i);
            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.getStyleClass().add("admin-row");

            Label icon = new Label("📄");
            VBox info = new VBox(2);
            HBox.setHgrow(info, Priority.ALWAYS);
            Label title = new Label(String.format(I18n.t("admin.contract_no"), c.optInt("id")));
            title.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label sub = new Label(c.optString("student_name", "User") + " ↔ " + c.optString("owner_name", "Owner"));
            sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
            info.getChildren().addAll(title, sub);

            Button viewBtn = new Button("👁");
            viewBtn.getStyleClass().addAll("btn-ghost", "btn-sm");
            int cId = c.optInt("id");
            viewBtn.setOnAction(e -> ContractViewer.show(cId));

            row.getChildren().addAll(icon, info, viewBtn);
            contractsPreview.getChildren().add(row);
        }
    }

    private void renderReportsPreview(JSONArray reports) {
        reportsPreview.getChildren().clear();
        if (reports == null || reports.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_reports"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            reportsPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(reports.length(), 3); i++) {
            JSONObject r = reports.getJSONObject(i);
            if (r.has("report")) r = r.getJSONObject("report");

            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.getStyleClass().add("admin-row");

            Label icon = new Label("🚩");
            VBox info = new VBox(2);
            HBox.setHgrow(info, Priority.ALWAYS);
            Label title = new Label(r.optString("type", "Report") + ": " + r.optString("reason", ""));
            title.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label sub = new Label(String.format(I18n.t("admin.by"), r.optString("reporter_name", "User"))
                    + " · " + r.optString("created_at", "").split("T")[0]);
            sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
            info.getChildren().addAll(title, sub);

            int reportId = r.optInt("id");
            int targetUserId = r.optInt("reported_user_id", r.optInt("target_user_id", 0));

            HBox actions = new HBox(6);
            Button btnDismiss = new Button("✓");
            btnDismiss.getStyleClass().addAll("btn-ghost", "btn-sm");
            btnDismiss.setStyle("-fx-text-fill: #16a34a;");
            btnDismiss.setTooltip(new Tooltip(I18n.t("admin.report.dismiss")));
            btnDismiss.setOnAction(e -> resolveReportFromPreview(reportId, btnDismiss));

            Button btnBan = new Button("🚫");
            btnBan.getStyleClass().addAll("btn-ghost", "btn-sm");
            btnBan.setStyle("-fx-text-fill: #dc2626;");
            btnBan.setTooltip(new Tooltip(I18n.t("admin.report.ban_user")));
            if (targetUserId > 0) {
                btnBan.setOnAction(e -> banUserFromPreview(targetUserId, reportId, btnBan));
            } else {
                btnBan.setDisable(true);
            }
            actions.getChildren().addAll(btnDismiss, btnBan);

            row.getChildren().addAll(icon, info, actions);
            reportsPreview.getChildren().add(row);
        }
    }

    private void resolveReportFromPreview(int reportId, Button btn) {
        btn.setDisable(true);
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject().put("report_id", reportId).put("action", "resolved");
                ApiClient.getInstance().put("/reports.php?id=" + reportId, body);
                Platform.runLater(this::loadStats);
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> btn.setDisable(false));
            }
        }).start();
    }

    private void banUserFromPreview(int userId, int reportId, Button btn) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                I18n.t("admin.report.ban_confirm"), ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        confirm.showAndWait().ifPresent(res -> {
            if (res != ButtonType.YES) return;
            btn.setDisable(true);
            new Thread(() -> {
                try {
                    JSONObject banBody = new JSONObject()
                            .put("status", "banned")
                            .put("notes", "Banned via report #" + reportId);
                    ApiClient.getInstance().put("/admin.php?user_id=" + userId, banBody);

                    JSONObject resolveBody = new JSONObject()
                            .put("report_id", reportId).put("action", "resolved");
                    ApiClient.getInstance().put("/reports.php?id=" + reportId, resolveBody);

                    Platform.runLater(this::loadStats);
                } catch (Exception e) {
                    e.printStackTrace();
                    Platform.runLater(() -> btn.setDisable(false));
                }
            }).start();
        });
    }

    private void renderTermsPreview(JSONArray terms) {
        termsPreview.getChildren().clear();
        if (terms == null || terms.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_terms"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            termsPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(terms.length(), 3); i++) {
            JSONObject t = terms.getJSONObject(i);
            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.getStyleClass().add("admin-row");

            Label icon = new Label("📄");
            VBox info = new VBox(2);
            HBox.setHgrow(info, Priority.ALWAYS);
            Label title = new Label(String.format(I18n.t("admin.contract_no"), t.optInt("contract_id", 0)));
            title.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label sub = new Label(String.format(I18n.t("admin.reason"), t.optString("reason", I18n.t("admin.no_reason"))));
            sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
            info.getChildren().addAll(title, sub);

            int requestId = t.optInt("id");
            HBox actions = new HBox(5);
            Button approveBtn = new Button("✅");
            approveBtn.getStyleClass().addAll("btn-ghost", "btn-sm");
            approveBtn.setOnAction(e -> handleApproveTerm(requestId));
            
            Button rejectBtn = new Button("❌");
            rejectBtn.getStyleClass().addAll("btn-ghost", "btn-sm");
            rejectBtn.setOnAction(e -> handleRejectTerm(requestId));
            
            actions.getChildren().addAll(approveBtn, rejectBtn);

            row.getChildren().addAll(icon, info, actions);
            termsPreview.getChildren().add(row);
        }
    }

    private void handleApproveTerm(int requestId) {
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject().put("request_id", requestId);
                JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=approve-termination", body);
                Platform.runLater(() -> {
                    if (resp.optBoolean("success")) {
                        new Alert(Alert.AlertType.INFORMATION, I18n.t("admin.termination_success")).show();
                        loadStats();
                    } else {
                        new Alert(Alert.AlertType.ERROR, resp.optString("error")).show();
                    }
                });
            } catch (Exception ex) {
                Platform.runLater(() -> new Alert(Alert.AlertType.ERROR, ex.getMessage()).show());
            }
        }).start();
    }

    private void handleRejectTerm(int requestId) {
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject().put("request_id", requestId);
                JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=reject-termination", body);
                Platform.runLater(() -> {
                    if (resp.optBoolean("success")) {
                        new Alert(Alert.AlertType.INFORMATION, I18n.t("admin.termination_success")).show();
                        loadStats();
                    } else {
                        new Alert(Alert.AlertType.ERROR, resp.optString("error")).show();
                    }
                });
            } catch (Exception ex) {
                Platform.runLater(() -> new Alert(Alert.AlertType.ERROR, ex.getMessage()).show());
            }
        }).start();
    }

    private void renderSubsPreview(JSONArray subs) {
        subsPreview.getChildren().clear();
        if (subs == null || subs.length() == 0) {
            Label empty = new Label(I18n.t("admin.no_subs"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            subsPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(subs.length(), 3); i++) {
            JSONObject s = subs.getJSONObject(i);
            HBox row = new HBox(12);
            row.setAlignment(Pos.CENTER_LEFT);
            row.getStyleClass().add("admin-row");

            Label icon = new Label("✨");
            VBox info = new VBox(2);
            HBox.setHgrow(info, Priority.ALWAYS);
            Label title = new Label(s.optString("user_name", "User") + " - " + I18n.t("reg." + s.optString("plan", "Basic").toLowerCase()));
            title.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            String status = s.optString("status", "active");
            String statusTxt = String.format(I18n.t("admin.status_label"), status.equalsIgnoreCase("active") ? I18n.t("admin.status.active") : status);
            Label sub = new Label(statusTxt + " · " + String.format(I18n.t("admin.expires_label"), s.optString("expires_at", "").split("T")[0]));
            sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
            info.getChildren().addAll(title, sub);

            row.getChildren().addAll(icon, info);
            subsPreview.getChildren().add(row);
        }
    }

    // ─── Navigation ───────────────────────────────────────
    @FXML private void gotoUsers()          { NavigationService.getInstance().navigateTo("AdminUsers.fxml"); }
    @FXML private void gotoListings()       { NavigationService.getInstance().navigateTo("Listings.fxml"); }
    @FXML private void gotoVerifications()  { NavigationService.getInstance().navigateTo("AdminVerifications.fxml"); }
    @FXML private void gotoReports()        { NavigationService.getInstance().navigateTo("AdminReports.fxml"); }
    @FXML private void gotoMessagesAdmin()  { NavigationService.getInstance().navigateTo("Messages.fxml"); }

    private String capitalize(String s) {
        if (s == null || s.length() == 0) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }
}
