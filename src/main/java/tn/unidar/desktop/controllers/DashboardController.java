package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.models.User;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import javafx.scene.control.*;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import tn.unidar.desktop.utils.I18n;
import tn.unidar.desktop.utils.CreativeImageLoader;
import tn.unidar.desktop.utils.ContractViewer;
import tn.unidar.desktop.utils.ContractStatusUi;

import java.net.URL;
import java.util.ResourceBundle;

public class DashboardController implements Initializable {

    @FXML private Label lblWelcome;
    @FXML private Label lblSaved, lblContracts, lblMessages, lblMatches;
    @FXML private Label lblProfileAvatar, lblProfileName, lblProfileEmail;
    @FXML private Label lblProfileRole, lblVerifStatus;
    @FXML private Label lblStatSaved2, lblStatContracts2, lblStatMessages2;
    @FXML private VBox  verificationBanner, premiumBanner;
    @FXML private VBox  messagesPreview, contractsList;
    @FXML private FlowPane savedListingsGrid;

    // Quick Action buttons
    @FXML private Button btnActionAddListing, btnActionMyListings, btnActionSearch;
    @FXML private Button btnActionRoommates, btnActionMessages, btnActionVerif, btnActionPremium;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        User user = AuthService.getInstance().getCurrentUser();
        if (user == null) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }

        String first = user.getFullName().split(" ")[0];
        lblWelcome.setText(I18n.t("dash.welcome").replace("%s", first));
        lblProfileName.setText(user.getFullName());
        lblProfileEmail.setText(user.getEmail());
        lblProfileRole.setText(capitalize(user.getRole()));
        lblProfileAvatar.setText(String.valueOf(user.getFullName().charAt(0)).toUpperCase());

        configureQuickActions(user.getRole());
        loadDashboardData();
    }

    private void configureQuickActions(String role) {
        boolean isOwner = "owner".equalsIgnoreCase(role);
        boolean isAdmin = "admin".equalsIgnoreCase(role);

        setVisible(btnActionAddListing, isOwner);
        setVisible(btnActionMyListings, isOwner);
        setVisible(btnActionSearch,    !isOwner && !isAdmin);
        setVisible(btnActionRoommates, !isOwner && !isAdmin);
        setVisible(btnActionVerif,     !isOwner && !isAdmin);
        setVisible(btnActionPremium,   !isOwner && !isAdmin);
        setVisible(btnActionMessages,  !isAdmin);
    }

    private void loadDashboardData() {
        new Thread(() -> {
            try {
                JSONObject savedResp = ApiClient.getInstance().get("/saved_listings.php");
                int savedCount = savedResp.optJSONArray("listings") != null
                        ? savedResp.getJSONArray("listings").length() : 0;

                JSONObject contractsResp = ApiClient.getInstance().get("/contracts.php?action=user-contracts");
                JSONArray contracts = contractsResp.optJSONArray("contracts");
                int contractCount = contracts != null ? contracts.length() : 0;

                JSONObject convoResp = ApiClient.getInstance().get("/conversations.php");
                JSONArray convos = convoResp.optJSONArray("conversations");
                int msgCount = convos != null ? convos.length() : 0;

                JSONObject verifResp = ApiClient.getInstance().get("/verifications.php");
                JSONObject verifObj = verifResp.optJSONObject("verification");
                String verifStatus = verifObj != null ? verifObj.optString("status", "none") : "none";

                JSONObject subResp = ApiClient.getInstance().get("/subscriptions.php");
                boolean isPremium = "active".equals(subResp.optString("subscription_status", "none"));

                JSONObject matchesResp = ApiClient.getInstance().get("/roommates.php?matches=1");
                JSONArray matchesArr = matchesResp.optJSONArray("matches");
                int matchCount = matchesArr != null ? matchesArr.length() : 0;

                final int finalMatchCount = matchCount;
                Platform.runLater(() -> {
                    updateStats(savedCount, contractCount, msgCount, finalMatchCount);
                    updateBanners(verifStatus, isPremium);
                    if (contracts != null) renderContracts(contracts);
                    renderMessages(convos);
                    loadSavedListings();
                });
            } catch (Exception e) {
                System.err.println("[Dashboard] Error loading data: " + e.getMessage());
            }
        }).start();
    }

    private void updateStats(int saved, int contracts, int msgs, int matches) {
        lblSaved.setText(String.valueOf(saved));
        lblContracts.setText(String.valueOf(contracts));
        lblMessages.setText(String.valueOf(msgs));
        lblMatches.setText(String.valueOf(matches));
        lblStatSaved2.setText(String.valueOf(saved));
        lblStatContracts2.setText(String.valueOf(contracts));
        lblStatMessages2.setText(String.valueOf(msgs));
    }

    private void updateBanners(String verifStatus, boolean isPremium) {
        boolean needsVerif = "none".equals(verifStatus) || "rejected".equals(verifStatus);
        verificationBanner.setVisible(needsVerif);
        verificationBanner.setManaged(needsVerif);
        premiumBanner.setVisible(!isPremium);
        premiumBanner.setManaged(!isPremium);

        String badge = switch (verifStatus) {
            case "approved" -> "✅ " + I18n.t("dash.verified");
            case "pending"  -> "⏳ " + I18n.t("dash.pending");
            case "rejected" -> "❌ " + I18n.t("dash.rejected");
            default         -> I18n.t("dash.unverified");
        };
        lblVerifStatus.setText(badge);
    }

    private void renderContracts(JSONArray contracts) {
        contractsList.getChildren().clear();
        if (contracts.isEmpty()) {
            Label empty = new Label(I18n.t("dash.no.contracts"));
            empty.getStyleClass().add("empty-state");
            contractsList.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(contracts.length(), 3); i++) {
            JSONObject c = contracts.getJSONObject(i);
            HBox row = new HBox(14);
            row.setStyle("-fx-background-color: white; -fx-background-radius: 10; " +
                         "-fx-padding: 14 16 14 16; " +
                         "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.05), 8, 0, 0, 2);");
            row.setAlignment(javafx.geometry.Pos.CENTER_LEFT);

            Label icon  = new Label("📋");
            icon.setStyle("-fx-font-size: 18px;");

            VBox info = new VBox(2);
            info.setStyle("-fx-hgrow: ALWAYS;");
            HBox.setHgrow(info, javafx.scene.layout.Priority.ALWAYS);
            Label titleLbl = new Label(I18n.t("dash.contract_no").replace("%s", String.valueOf(c.optInt("id", i + 1))));
            titleLbl.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label sub = new Label(c.optString("listing_title", "Property") +
                                  " • " + c.optInt("monthly_rent", 0) + " TND/mo");
            sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
            info.getChildren().addAll(titleLbl, sub);

            String status = c.optString("status", "pending");
            boolean isTermRequested = ContractStatusUi.isTerminationPending(c);

            Label badge = new Label(isTermRequested
                    ? I18n.t("dash.termination_requested")
                    : ContractStatusUi.formatLabel(status));
            badge.getStyleClass().addAll("badge", isTermRequested ? "badge-warning" : ContractStatusUi.badgeClass(status));

            row.getChildren().addAll(icon, info, badge);

            int cId = c.optInt("id", 0);
            if (cId > 0) {
                Button btnView = new Button("👁");
                btnView.getStyleClass().addAll("btn-ghost", "btn-sm");
                btnView.setTooltip(new Tooltip(I18n.t("contract.view.title")));
                btnView.setOnAction(e -> ContractViewer.show(cId));

                if (ContractStatusUi.needsPayment(status) && !isTermRequested) {
                    Button btnPay = new Button("💳");
                    btnPay.getStyleClass().addAll("btn-primary", "btn-sm");
                    btnPay.setTooltip(new Tooltip(I18n.t("dash.pay_now")));
                    double rent = c.optDouble("monthly_rent", 0);
                    btnPay.setOnAction(e -> handlePayment(cId, rent));
                    row.getChildren().add(btnPay);
                }

                if (ContractStatusUi.canRequestTermination(status) && !isTermRequested) {
                    Button btnTerm = new Button("🚫");
                    btnTerm.getStyleClass().addAll("btn-ghost", "btn-sm");
                    btnTerm.setTooltip(new Tooltip(I18n.t("dash.request_termination")));
                    btnTerm.setOnAction(e -> handleRequestTermination(cId));
                    row.getChildren().add(btnTerm);
                }

                row.getChildren().add(btnView);
            }

            contractsList.getChildren().add(row);
        }
    }

    private void renderMessages(JSONArray convos) {
        messagesPreview.getChildren().clear();
        if (convos == null || convos.isEmpty()) {
            Label empty = new Label(I18n.t("dash.no.messages"));
            empty.getStyleClass().add("empty-state");
            empty.setMaxWidth(Double.MAX_VALUE);
            empty.setAlignment(javafx.geometry.Pos.CENTER);
            messagesPreview.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < Math.min(convos.length(), 3); i++) {
            JSONObject c = convos.getJSONObject(i);
            HBox row = new HBox(12);
            row.setStyle("-fx-background-color: #f8fafc; -fx-background-radius: 10; -fx-padding: 12 14 12 14;");
            row.setAlignment(javafx.geometry.Pos.CENTER_LEFT);

            String name = c.optString("other_user_name", "User");
            Label avatar = new Label(name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase());
            avatar.setStyle("-fx-background-color: #4f46e5; -fx-background-radius: 16; " +
                            "-fx-min-width: 32; -fx-min-height: 32; " +
                            "-fx-text-fill: white; -fx-font-weight: bold; -fx-font-size: 13px; -fx-alignment: CENTER;");

            VBox info = new VBox(2);
            HBox.setHgrow(info, javafx.scene.layout.Priority.ALWAYS);
            Label sender = new Label(name);
            sender.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
            Label preview = new Label(c.optString("last_message", "No messages yet"));
            preview.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
            info.getChildren().addAll(sender, preview);

            row.getChildren().addAll(avatar, info);
            messagesPreview.getChildren().add(row);
        }
        Button viewAll = new Button(I18n.t("dash.viewmsgs"));
        viewAll.getStyleClass().addAll("btn-ghost", "btn-sm");
        viewAll.setOnAction(e -> NavigationService.getInstance().navigateTo("Messages.fxml"));
        messagesPreview.getChildren().add(viewAll);
    }

    private void loadSavedListings() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/saved_listings.php");
                JSONArray arr = resp.optJSONArray("listings");
                Platform.runLater(() -> {
                    savedListingsGrid.getChildren().clear();
                    if (arr == null || arr.isEmpty()) {
                        Label e = new Label(I18n.t("dash.no.saved"));
                        e.getStyleClass().add("empty-state");
                        e.setWrapText(true);
                        e.setMaxWidth(500);
                        savedListingsGrid.getChildren().add(e);
                        return;
                    }
                    for (int i = 0; i < Math.min(arr.length(), 6); i++) {
                        JSONObject l = arr.getJSONObject(i);
                        savedListingsGrid.getChildren().add(createMiniCard(l));
                    }
                });
            } catch (Exception e) {
                System.err.println("[Dashboard] Error loading saved listings: " + e.getMessage());
            }
        }).start();
    }

    private VBox createMiniCard(JSONObject l) {
        String thumb = l.optString("thumbnail", "");

        VBox card = new VBox(6);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 10; " +
                      "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.06), 8, 0, 0, 2); " +
                      "-fx-cursor: hand; -fx-pref-width: 180;");
        card.setOnMouseClicked(e -> NavigationService.getInstance().navigateTo("ListingDetail.fxml", l.optInt("id")));

        javafx.scene.layout.StackPane imgPane = new javafx.scene.layout.StackPane();
        imgPane.setMinHeight(100); imgPane.setMaxHeight(100);
        javafx.scene.layout.Region img = new javafx.scene.layout.Region();
        img.setStyle("-fx-background-color: linear-gradient(135deg, #e0e7ff, #ede9fe); " +
                     "-fx-background-radius: 10 10 0 0; -fx-min-height: 100;");
        imgPane.getChildren().add(img);
        String cardTitle = l.optString("title", "Listing");
        String cardType  = l.optString("property_type", "apartment");
        if (!thumb.isEmpty()) {
            String fullUrl = ApiClient.resolveImageUrl(thumb, cardTitle, cardType);
            CreativeImageLoader.load(imgPane, fullUrl, 180, 100, cardTitle, cardType);
        } else {
            CreativeImageLoader.load(imgPane, "", 180, 100, cardTitle, cardType);
        }

        VBox info = new VBox(3);
        info.setStyle("-fx-padding: 8 10 10 10;");
        Label titleLbl = new Label(cardTitle);
        titleLbl.setStyle("-fx-font-weight: bold; -fx-font-size: 12px; -fx-text-fill: #0f172a;");
        titleLbl.setWrapText(true);
        Label price = new Label(l.optDouble("price", 0) + " TND");
        price.setStyle("-fx-text-fill: #4f46e5; -fx-font-weight: bold; -fx-font-size: 13px;");
        info.getChildren().addAll(titleLbl, price);

        card.getChildren().addAll(imgPane, info);
        return card;
    }

    // ─── Quick action buttons ──────────────────────────────
    @FXML private void gotoListings()      { NavigationService.getInstance().navigateTo("Listings.fxml"); }
    @FXML private void gotoRoommates()     { NavigationService.getInstance().navigateTo("Roommates.fxml"); }
    @FXML private void gotoMessages()      { NavigationService.getInstance().navigateTo("Messages.fxml"); }
    @FXML private void gotoVerification()  { NavigationService.getInstance().navigateTo("Verification.fxml"); }
    @FXML private void gotoSubscription()  { NavigationService.getInstance().navigateTo("Subscription.fxml"); }

    @FXML
    private void handleQuickAdd() {
        NavigationService.getInstance().setData("OPEN_ADD_MODAL");
        NavigationService.getInstance().navigateTo("OwnerListings.fxml");
    }

    @FXML
    private void handleQuickMyList() {
        NavigationService.getInstance().navigateTo("OwnerListings.fxml");
    }

    // ─── Payment Dialog (full card form) ──────────────────
    private void handlePayment(int contractId, double amount) {
        Stage dia = new Stage();
        dia.initModality(Modality.APPLICATION_MODAL);
        dia.setTitle(I18n.t("payment.title"));
        dia.setResizable(false);

        VBox root = new VBox(16);
        root.setStyle("-fx-padding: 32; -fx-background-color: white;");
        root.setPrefWidth(460);

        // Title
        Label icon = new Label("💳");
        icon.setStyle("-fx-font-size: 26px;");
        Label titleLbl = new Label(I18n.t("dash.pay_now"));
        titleLbl.setStyle("-fx-font-size: 20px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");
        HBox titleRow = new HBox(10, icon, titleLbl);
        titleRow.setAlignment(Pos.CENTER_LEFT);

        // Summary
        double commission = amount * 0.05;
        double total = amount + commission;
        VBox summaryBox = new VBox(6);
        summaryBox.setStyle("-fx-background-color: #eef2ff; -fx-background-radius: 10; -fx-padding: 14 18;");
        Label lRent  = new Label(I18n.t("monthly_rent") + ": " + amount + " TND");
        lRent.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a;");
        Label lComm  = new Label(I18n.t("platform_commission") + " (5%): " + String.format("%.2f", commission) + " TND");
        lComm.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
        Label lTotal = new Label(I18n.t("total_due") + ": " + String.format("%.2f", total) + " TND");
        lTotal.setStyle("-fx-font-weight: bold; -fx-text-fill: #059669; -fx-font-size: 15px;");
        summaryBox.getChildren().addAll(lRent, lComm, new Separator(), lTotal);

        // Card number field (max 16 digits)
        Label lblCard = new Label(I18n.t("card_number"));
        lblCard.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        TextField txtCard = new TextField();
        txtCard.setPromptText("0000 0000 0000 0000");
        txtCard.getStyleClass().add("form-input");
        txtCard.textProperty().addListener((obs, oldV, newV) -> {
            if (newV == null) return;
            String digits = newV.replaceAll("\\D", "");
            if (digits.length() > 16) { txtCard.setText(oldV); return; }
            StringBuilder fmt = new StringBuilder();
            for (int i = 0; i < digits.length(); i++) {
                if (i > 0 && i % 4 == 0) fmt.append(' ');
                fmt.append(digits.charAt(i));
            }
            String f = fmt.toString();
            if (!f.equals(newV)) { txtCard.setText(f); txtCard.positionCaret(f.length()); }
        });

        // Cardholder name
        Label lblName = new Label("Cardholder Name");
        lblName.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        TextField txtName = new TextField(AuthService.getInstance().isLoggedIn()
                ? AuthService.getInstance().getCurrentUser().getFullName() : "");
        txtName.setPromptText("Name as on card");
        txtName.getStyleClass().add("form-input");

        // Expiry + CVV row
        HBox row2 = new HBox(12);
        VBox expiryBox = new VBox(4);
        Label lblExp = new Label(I18n.t("expiry"));
        lblExp.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        TextField txtExpiry = new TextField();
        txtExpiry.setPromptText("MM/YY");
        txtExpiry.getStyleClass().add("form-input");
        expiryBox.getChildren().addAll(lblExp, txtExpiry);

        VBox cvvBox = new VBox(4);
        Label lblCvv = new Label(I18n.t("cvv"));
        lblCvv.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        PasswordField txtCvv = new PasswordField();
        txtCvv.setPromptText("123");
        txtCvv.getStyleClass().add("form-input");
        txtCvv.textProperty().addListener((obs, oldV, newV) -> {
            if (newV != null && newV.replaceAll("\\D", "").length() > 4) txtCvv.setText(oldV);
        });
        cvvBox.getChildren().addAll(lblCvv, txtCvv);
        HBox.setHgrow(expiryBox, javafx.scene.layout.Priority.ALWAYS);
        HBox.setHgrow(cvvBox, javafx.scene.layout.Priority.ALWAYS);
        row2.getChildren().addAll(expiryBox, cvvBox);

        // Error box
        VBox errBox = new VBox();
        errBox.setVisible(false); errBox.setManaged(false);
        Label errLbl = new Label();
        errLbl.setStyle("-fx-text-fill: #dc2626;");
        errBox.getChildren().add(errLbl);

        // Pay button
        Button btnPay = new Button("💳  " + I18n.t("pay_btn") + " " + String.format("%.2f", total) + " TND");
        btnPay.getStyleClass().add("btn-primary");
        btnPay.setMaxWidth(Double.MAX_VALUE);
        btnPay.setPrefHeight(48);
        btnPay.setOnAction(e -> {
            String cardNum = txtCard.getText().replaceAll("\\s", "");
            String expiry  = txtExpiry.getText().trim();
            String cvv     = txtCvv.getText().trim();
            if (cardNum.length() < 13) { errLbl.setText("⚠️ " + I18n.t("err.card_number")); errBox.setVisible(true); errBox.setManaged(true); return; }
            if (!expiry.matches("\\d{2}/\\d{2}")) { errLbl.setText("⚠️ " + I18n.t("err.expiry")); errBox.setVisible(true); errBox.setManaged(true); return; }
            if (cvv.length() < 3) { errLbl.setText("⚠️ " + I18n.t("err.cvv")); errBox.setVisible(true); errBox.setManaged(true); return; }
            errBox.setVisible(false); errBox.setManaged(false);
            btnPay.setDisable(true);
            btnPay.setText(I18n.t("processing"));
            new Thread(() -> {
                try {
                    JSONObject body = new JSONObject()
                            .put("contract_id", contractId)
                            .put("payment_method", "card")
                            .put("card_last4", cardNum.substring(cardNum.length() - 4));
                    JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=process-payment", body);
                    Platform.runLater(() -> {
                        if (resp.optBoolean("success")) {
                            dia.close();
                            Alert ok = new Alert(Alert.AlertType.INFORMATION);
                            ok.setTitle(I18n.t("payment.success.title"));
                            ok.setHeaderText("🎉 " + I18n.t("payment.success.msg"));
                            ok.setContentText(I18n.t("contract") + " #" + contractId
                                    + " — " + I18n.t("amount") + ": " + String.format("%.2f", total) + " TND"
                                    + "\n" + I18n.t("card_ending") + ": " + cardNum.substring(cardNum.length() - 4));
                            ok.showAndWait();
                            loadDashboardData();
                        } else {
                            errLbl.setText("⚠️ " + resp.optString("error", I18n.t("payment.err")));
                            errBox.setVisible(true); errBox.setManaged(true);
                            btnPay.setDisable(false);
                            btnPay.setText("💳  " + I18n.t("pay_btn") + " " + String.format("%.2f", total) + " TND");
                        }
                    });
                } catch (Exception ex) {
                    Platform.runLater(() -> {
                        errLbl.setText("⚠️ " + I18n.t("common.error"));
                        errBox.setVisible(true); errBox.setManaged(true);
                        btnPay.setDisable(false);
                        btnPay.setText("💳  " + I18n.t("pay_btn") + " " + String.format("%.2f", total) + " TND");
                    });
                }
            }).start();
        });

        Button btnCancel = new Button(I18n.t("clear"));
        btnCancel.getStyleClass().add("btn-ghost");
        btnCancel.setMaxWidth(Double.MAX_VALUE);
        btnCancel.setOnAction(e -> dia.close());

        root.getChildren().addAll(titleRow, summaryBox, lblCard, txtCard, lblName, txtName, row2, errBox, btnPay, btnCancel);

        Scene scene = new Scene(root);
        try { scene.getStylesheets().add(getClass().getResource("/css/unidar-pages.css").toExternalForm()); } catch (Exception ignored) {}
        dia.setScene(scene);
        dia.show();
    }

    private void handleRequestTermination(int contractId) {
        TextInputDialog dia = new TextInputDialog();
        dia.setTitle(I18n.t("dash.request_termination"));
        dia.setHeaderText(I18n.t("dash.request_termination"));
        dia.setContentText(I18n.t("dash.termination_reason"));

        dia.showAndWait().ifPresent(reason -> {
            new Thread(() -> {
                try {
                    JSONObject body = new JSONObject().put("contract_id", contractId).put("reason", reason);
                    JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=request-termination", body);
                    Platform.runLater(() -> {
                        if (resp.optBoolean("success")) {
                            new Alert(Alert.AlertType.INFORMATION, I18n.t("dash.termination_requested")).show();
                            loadDashboardData();
                        } else {
                            new Alert(Alert.AlertType.ERROR, resp.optString("error")).show();
                        }
                    });
                } catch (Exception ex) {
                    Platform.runLater(() -> new Alert(Alert.AlertType.ERROR, ex.getMessage()).show());
                }
            }).start();
        });
    }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }

    private void setVisible(javafx.scene.Node node, boolean visible) {
        if (node != null) {
            node.setVisible(visible);
            node.setManaged(visible);
        }
    }
}
