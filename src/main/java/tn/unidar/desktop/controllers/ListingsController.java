package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.*;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;
import tn.unidar.desktop.utils.SvgUtils;
import tn.unidar.desktop.utils.CreativeImageLoader;

import java.net.URL;
import java.util.ResourceBundle;

public class ListingsController implements Initializable {

    @FXML private FlowPane  listingsGrid;
    @FXML private TextField txtSearch;
    @FXML private TextField txtMinPrice, txtMaxPrice;
    @FXML private ComboBox<String> comboBedrooms, comboType, comboGender, comboSort;
    @FXML private VBox  loadingPane, emptyPane;
    @FXML private Label lblResultCount;

    private JSONArray allListings = new JSONArray();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        translateUI();
        loadListings(null);
    }

    private void translateUI() {
        txtSearch.setPromptText(I18n.t("common.search"));
        
        comboBedrooms.getItems().setAll(I18n.t("list.bedrooms.any"), "1", "2", "3", "4+");
        comboBedrooms.setValue(I18n.t("list.bedrooms.any"));
        
        comboType.getItems().setAll(I18n.t("list.type.all"), "apartment", "studio", "villa", "room", "shared");
        comboType.setValue(I18n.t("list.type.all"));
        
        comboGender.getItems().setAll(I18n.t("list.gender.none"), "male", "female", "mixed");
        comboGender.setValue(I18n.t("list.gender.none"));
        
        comboSort.getItems().setAll(
            I18n.t("list.sort.newest"), 
            I18n.t("list.sort.price_asc"), 
            I18n.t("list.sort.price_desc")
        );
        comboSort.setValue(I18n.t("list.sort.newest"));
    }

    private void loadListings(String query) {
        setLoading(true);
        new Thread(() -> {
            try {
                // Next.js API expects a query object; Listings.java handles it.
                // Replicate what unidarreact does: call /listings without 'public=true' 
                // but let the session tokens handle access.
                // Standardlistings endpoint - Next.js maps it to pages/api/listings/index.js
                StringBuilder url = new StringBuilder("/listings.php?");
                if (query != null && !query.isBlank()) url.append("q=").append(java.net.URLEncoder.encode(query, "UTF-8"));
                
                System.out.println("[ListingsController] Fetching from: " + url.toString());

                // Price filters
                String minP = txtMinPrice.getText().trim();
                String maxP = txtMaxPrice.getText().trim();
                if (!minP.isEmpty()) url.append("&min_price=").append(minP);
                if (!maxP.isEmpty()) url.append("&max_price=").append(maxP);

                // Combo filters
                String beds = comboBedrooms.getValue();
                if (beds != null && !beds.equals(I18n.t("list.bedrooms.any")))
                    url.append("&bedrooms=").append(beds.replace("+", ""));

                String type = comboType.getValue();
                if (type != null && !type.equals(I18n.t("list.type.all"))) {
                    String pType = type.equals("room") ? "shared_room" : type;
                    url.append("&property_type=").append(pType);
                }

                String gender = comboGender.getValue();
                if (gender != null && !gender.equals(I18n.t("list.gender.none")))
                    url.append("&gender_preference=").append(gender);

                JSONObject resp = ApiClient.getInstance().get(url.toString());
                allListings = resp.optJSONArray("listings");
                if (allListings == null) allListings = new JSONArray();

                final JSONArray result = allListings;
                Platform.runLater(() -> renderListings(result));
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    setLoading(false);
                    showEmpty(true);
                });
            }
        }).start();
    }

    private void renderListings(JSONArray arr) {
        setLoading(false);
        listingsGrid.getChildren().clear();

        if (arr.isEmpty()) {
            showEmpty(true);
            return;
        }
        showEmpty(false);
        int count = arr.length();
        String foundTxt = count + " " + (count == 1 ? I18n.t("list.found.one") : I18n.t("list.found.many"));
        lblResultCount.setText(foundTxt);

        for (int i = 0; i < arr.length(); i++) {
            JSONObject obj = arr.getJSONObject(i);
            listingsGrid.getChildren().add(buildCard(obj));
        }
    }

    private VBox buildCard(JSONObject obj) {
        int id = obj.optInt("id", 0);
        String title   = obj.optString("title",   I18n.t("list.unnamed"));
        String address = obj.optString("address", I18n.t("list.unknown_loc"));
        double price   = obj.optDouble("price", 0);
        int beds       = obj.optInt("bedrooms", 0);
        String type    = obj.optString("property_type", "");
        String gender  = obj.optString("gender_preference", "");
        boolean saved  = obj.optBoolean("is_saved", false);
        // Prefer full URL thumbnail returned by PHP, fall back to resolving relative path
        String thumb = obj.optString("thumbnail", "");
        String imageUrl = ApiClient.resolveImageUrl(thumb, title, type);

        VBox card = new VBox(0);
        card.getStyleClass().add("listing-card");
        card.setPrefWidth(300);
        card.setStyle("-fx-cursor: hand;");
        card.setOnMouseClicked(e -> openDetail(id));

        // Image
        StackPane imgLayer = new StackPane();
        imgLayer.getStyleClass().add("listing-img-container");
        imgLayer.setMinHeight(180);
        imgLayer.setMaxHeight(180);

        Region placeholder = new Region();
        placeholder.getStyleClass().add("listing-img-placeholder");
        imgLayer.getChildren().add(placeholder);

        // thumbnail is already a full URL when returned by updated PHP API
        String fullUrl = thumb != null && !thumb.isEmpty() ? 
                (thumb.startsWith("http") ? thumb : ApiClient.resolveImageUrl(thumb)) :
                SvgUtils.getPlaceholder(title, type);
        
        // Integrated Creative Image Loading System
        CreativeImageLoader.load(imgLayer, fullUrl, 300, 180, title, type);

        // Badge row over image
        HBox badges = new HBox(6);
        badges.setStyle("-fx-padding: 10 10 0 10;");
        if (!type.isEmpty()) {
            Label tBadge = new Label(type);
            tBadge.getStyleClass().addAll("badge", "badge-primary");
            badges.getChildren().add(tBadge);
        }
        if (!gender.isEmpty() && !gender.equals("no_preference") && !gender.equals("none")) {
            Label gBadge = new Label(gender);
            gBadge.getStyleClass().addAll("badge", "badge-neutral");
            badges.getChildren().add(gBadge);
        }

        // Save button (top-right)
        Button btnSave = new Button(saved ? "❤️" : "🤍");
        btnSave.setStyle("-fx-background-color: rgba(255,255,255,0.9); -fx-background-radius: 20; " +
                         "-fx-padding: 4 8 4 8; -fx-cursor: hand; -fx-font-size: 16px;");
        btnSave.setOnMouseClicked(e -> {
            e.consume();
            toggleSave(id, btnSave);
        });

        // Overlay badges + save on top of image
        StackPane imgStack = new StackPane(imgLayer, badges, btnSave);
        StackPane.setAlignment(badges, javafx.geometry.Pos.TOP_LEFT);
        StackPane.setAlignment(btnSave, javafx.geometry.Pos.TOP_RIGHT);
        StackPane.setMargin(btnSave, new javafx.geometry.Insets(10));

        // Info
        VBox info = new VBox(4);
        info.setStyle("-fx-padding: 14 16 16 16;");

        Label lTitle = new Label(title);
        lTitle.getStyleClass().add("listing-title");
        lTitle.setWrapText(true);

        Label lAddr = new Label("📍 " + address);
        lAddr.getStyleClass().add("listing-address");

        HBox bottom = new HBox(10);
        bottom.setAlignment(javafx.geometry.Pos.CENTER_LEFT);
        bottom.setStyle("-fx-padding: 6 0 0 0;");

        Label lPrice = new Label(price + " TND/mo");
        lPrice.getStyleClass().add("listing-price");
        HBox.setHgrow(lPrice, Priority.ALWAYS);

        Label lBeds = new Label(beds + " 🛏");
        lBeds.getStyleClass().add("listing-meta");
        bottom.getChildren().addAll(lPrice, lBeds);

        Button btn = new Button(I18n.t("common.details"));
        btn.getStyleClass().addAll("btn-primary", "btn-sm");
        btn.setMaxWidth(Double.MAX_VALUE);
        btn.setOnAction(e -> openDetail(id));
        btn.setStyle("-fx-font-size: 12px; -fx-padding: 7 14 7 14; -fx-margin: 4 0 0 0;");

        info.getChildren().addAll(lTitle, lAddr, bottom, btn);
        card.getChildren().addAll(imgStack, info);
        return card;
    }

    private void openDetail(int id) {
        NavigationService.getInstance().navigateTo("ListingDetail.fxml", id);
    }

    private void toggleSave(int listingId, Button btn) {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject().put("listing_id", listingId);
                boolean isSaved = "❤️".equals(btn.getText());
                if (isSaved) {
                    ApiClient.getInstance().delete("/saved_listings.php?listing_id=" + listingId);
                } else {
                    ApiClient.getInstance().post("/saved_listings.php", body);
                }
                Platform.runLater(() -> btn.setText(isSaved ? "🤍" : "❤️"));
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    // ─── Filter / Search ──────────────────────────────────
    @FXML private void handleSearch() { loadListings(txtSearch.getText()); }
    @FXML private void handleFilter() { loadListings(txtSearch.getText()); }

    @FXML
    private void clearFilters() {
        txtSearch.clear();
        txtMinPrice.clear();
        txtMaxPrice.clear();
        comboBedrooms.setValue(I18n.t("list.bedrooms.any"));
        comboType.setValue(I18n.t("list.type.all"));
        comboGender.setValue(I18n.t("list.gender.none"));
        loadListings(null);
    }

    // ─── UI helpers ───────────────────────────────────────
    private void setLoading(boolean loading) {
        loadingPane.setVisible(loading);
        loadingPane.setManaged(loading);
        if (loading) {
            listingsGrid.getChildren().clear();
            lblResultCount.setText(I18n.t("common.loading"));
        }
    }

    private void showEmpty(boolean empty) {
        emptyPane.setVisible(empty);
        emptyPane.setManaged(empty);
        if (empty) lblResultCount.setText(I18n.t("list.nofound"));
    }
}
