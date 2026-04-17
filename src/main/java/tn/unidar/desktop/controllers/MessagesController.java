package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Insets;
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
import java.util.ResourceBundle;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class MessagesController implements Initializable {

    @FXML private VBox     convoList;
    @FXML private TextField txtSearchConvo;
    @FXML private VBox     noConvoPane, chatPane;
    @FXML private VBox     msgContainer;
    @FXML private ScrollPane msgScrollPane;
    @FXML private Label    lblChatAvatar, lblChatName, lblChatStatus;
    @FXML private TextField txtMessage;

    private int activeConversationId = -1;
    private int activeOtherUserId    = -1;
    private ScheduledExecutorService poller;
    private JSONArray allConvos = new JSONArray();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }

        // Check if we came with a target user (from Roommates "Message" button)
        Object data = NavigationService.getInstance().consumeData();
        if (data instanceof Integer targetUserId) {
            // Open or create conversation with this user
            openConvoWithUser(targetUserId);
        }

        loadConversations();
        startPolling();

        // Stop polling when this view is removed from the scene (navigation away)
        convoList.sceneProperty().addListener((obs, oldScene, newScene) -> {
            if (newScene == null) stopPolling();
        });
    }

    private void loadConversations() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/conversations.php");
                allConvos = resp.optJSONArray("conversations");
                if (allConvos == null) allConvos = new JSONArray();
                final JSONArray convos = allConvos;
                Platform.runLater(() -> renderConvoList(convos, ""));
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    private void renderConvoList(JSONArray convos, String filter) {
        convoList.getChildren().clear();
        if (convos.isEmpty()) {
            Label empty = new Label(I18n.t("msg.empty"));
            empty.setStyle("-fx-text-fill: #94a3b8; -fx-font-size: 13px; -fx-text-alignment: center; -fx-padding: 30 20 20 20;");
            empty.setWrapText(true);
            convoList.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < convos.length(); i++) {
            JSONObject c = convos.getJSONObject(i);
            String name = c.optString("other_user_name", "User");
            if (!filter.isEmpty() && !name.toLowerCase().contains(filter.toLowerCase())) continue;
            convoList.getChildren().add(buildConvoItem(c));
        }
    }

    private VBox buildConvoItem(JSONObject c) {
        int    cid      = c.optInt("id", 0);
        String name     = c.optString("other_user_name", "User");
        String preview  = c.optString("last_message", I18n.t("msg.start_convo"));
        String time     = formatTime(c.optString("last_message_time", ""));
        int    otherId  = c.optInt("other_user_id", 0);

        VBox item = new VBox(3);
        item.getStyleClass().add("convo-item");
        item.setUserData(cid); // Store cid for selection lookup
        if (cid == activeConversationId) item.getStyleClass().add("selected");
        item.setOnMouseClicked(e -> openConversation(cid, otherId, name));

        HBox row = new HBox(10);
        row.setAlignment(Pos.CENTER_LEFT);

        Label avatar = new Label(name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase());
        avatar.getStyleClass().add("roommate-avatar");
        avatar.setStyle("-fx-min-width:40; -fx-min-height:40; -fx-max-width:40; -fx-max-height:40; -fx-font-size:16px;");

        VBox info = new VBox(2);
        HBox.setHgrow(info, Priority.ALWAYS);
        Label lName = new Label(name);
        lName.getStyleClass().add("convo-name");
        Label lPreview = new Label(preview);
        lPreview.getStyleClass().add("convo-preview");
        lPreview.setMaxWidth(180);
        info.getChildren().addAll(lName, lPreview);

        Label lTime = new Label(time);
        lTime.getStyleClass().add("convo-time");

        row.getChildren().addAll(avatar, info, lTime);
        item.getChildren().add(row);
        return item;
    }

    private void openConvoWithUser(int userId) {
        new Thread(() -> {
            try {
                // Ensure conversation exists
                JSONObject body = new JSONObject().put("recipient_id", userId);
                JSONObject resp = ApiClient.getInstance().post("/conversations.php?action=create", body);
                int convId = resp.optInt("id", -1);
                String uname = resp.optString("other_user_name", "User");
                if (convId != -1) {
                    Platform.runLater(() -> openConversation(convId, userId, uname));
                }
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    private void openConversation(int convId, int otherId, String name) {
        activeConversationId = convId;
        activeOtherUserId    = otherId;

        // Update UI
        noConvoPane.setVisible(false); noConvoPane.setManaged(false);
        chatPane.setVisible(true);     chatPane.setManaged(true);
        lblChatName.setText(name);
        lblChatAvatar.setText(name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase());
        lblChatStatus.setText(I18n.t("msg.active_now"));

        // Re-style conv items
        convoList.getChildren().forEach(n -> {
            n.getStyleClass().remove("selected");
            if (n.getUserData() instanceof Integer id && id == convId) {
                n.getStyleClass().add("selected");
            }
        });

        loadMessages(convId);
    }

    private void loadMessages(int convId) {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/messages.php?id=" + convId);
                JSONArray msgs = resp.optJSONArray("messages");
                if (msgs == null) msgs = new JSONArray();
                final JSONArray messages = msgs;
                Platform.runLater(() -> renderMessages(messages));
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    private void renderMessages(JSONArray msgs) {
        msgContainer.getChildren().clear();
        int myId = AuthService.getInstance().getCurrentUser().getId();

        for (int i = 0; i < msgs.length(); i++) {
            JSONObject m = msgs.getJSONObject(i);
            boolean isMine = m.optInt("sender_id", 0) == myId;
            msgContainer.getChildren().add(buildBubble(m.optString("content", ""), isMine,
                    formatTime(m.optString("created_at", ""))));
        }

        // Auto-scroll to bottom
        Platform.runLater(() -> msgScrollPane.setVvalue(1.0));
    }

    private HBox buildBubble(String text, boolean mine, String time) {
        HBox row = new HBox(8); // added spacing for avatar
        row.setAlignment(mine ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);
        row.setMaxWidth(Double.MAX_VALUE);
        row.setPadding(new Insets(4, 16, 4, 16));

        Label avatar = new Label(mine ? "👤" : "💬");
        avatar.setPrefSize(32, 32);
        avatar.setMinSize(32, 32);
        avatar.setAlignment(Pos.CENTER);
        avatar.setStyle("-fx-background-color: " + (mine ? "#4f46e5" : "#e2e8f0") + "; "
                      + "-fx-text-fill: " + (mine ? "white" : "#475569") + "; "
                      + "-fx-background-radius: 50%; "
                      + "-fx-font-size: 15px;");

        Label bubble = new Label(text);
        bubble.setWrapText(true);
        bubble.setMaxWidth(340);
        bubble.setPrefWidth(Region.USE_COMPUTED_SIZE);
        bubble.getStyleClass().add(mine ? "msg-bubble-me" : "msg-bubble-other");
        bubble.setStyle("-fx-text-fill: " + (mine ? "white" : "#1e293b") + ";");

        Label timeLabel = new Label(time);
        timeLabel.getStyleClass().add("msg-time");

        VBox wrapper = new VBox(3);
        wrapper.setAlignment(mine ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);
        wrapper.setMaxWidth(360);
        wrapper.getChildren().addAll(bubble, timeLabel);

        if (mine) {
            row.getChildren().addAll(wrapper, avatar);
        } else {
            row.getChildren().addAll(avatar, wrapper);
        }

        return row;
    }


    @FXML
    private void sendMessage() {
        String text = txtMessage.getText().trim();
        if (text.isEmpty() || activeConversationId == -1) return;
        txtMessage.clear();

        // Local Echo
        msgContainer.getChildren().add(buildBubble(text, true, "Sending..."));
        Platform.runLater(() -> msgScrollPane.setVvalue(1.0));

        new Thread(() -> {
            try {
                JSONObject body = new JSONObject()
                        .put("conversation_id", activeConversationId)
                        .put("message", text);
                ApiClient.getInstance().post("/messages.php", body);
                Platform.runLater(() -> {
                    loadMessages(activeConversationId);
                    loadConversations();
                });
            } catch (Exception e) { 
                e.printStackTrace(); 
                Platform.runLater(() -> {
                    // Could add a failed status here
                    loadMessages(activeConversationId);
                });
            }
        }).start();
    }

    @FXML
    private void filterConversations() {
        renderConvoList(allConvos, txtSearchConvo.getText());
    }

    private void startPolling() {
        poller = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r);
            t.setDaemon(true);
            return t;
        });
        poller.scheduleAtFixedRate(() -> {
            loadConversations();
            if (activeConversationId != -1) loadMessages(activeConversationId);
        }, 5, 5, TimeUnit.SECONDS);
    }

    public void stopPolling() {
        if (poller != null) poller.shutdownNow();
    }

    private String formatTime(String iso) {
        if (iso == null || iso.isEmpty()) return "";
        try {
            return iso.length() >= 16 ? iso.substring(11, 16) : iso;
        } catch (Exception e) { return ""; }
    }
}
