package tn.unidar.desktop.models;

public class Message {
    private int    id;
    private int    conversationId;
    private int    senderId;
    private String senderName;
    private String content;
    private String createdAt;

    public Message(int id, int senderId, String content, String createdAt) {
        this.id        = id;
        this.senderId  = senderId;
        this.content   = content;
        this.createdAt = createdAt;
    }

    public int    getId()             { return id; }
    public int    getConversationId() { return conversationId; }
    public void   setConversationId(int c) { this.conversationId = c; }
    public int    getSenderId()       { return senderId; }
    public String getSenderName()     { return senderName; }
    public void   setSenderName(String n) { this.senderName = n; }
    public String getContent()        { return content; }
    public String getCreatedAt()      { return createdAt; }
}
