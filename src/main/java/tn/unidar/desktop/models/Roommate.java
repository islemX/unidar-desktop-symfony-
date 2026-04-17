package tn.unidar.desktop.models;

public class Roommate {
    private int    id;
    private String fullName;
    private String email;
    private String university;
    private String gender;
    private int    compatibilityScore;
    private String cleanliness;
    private String sleepSchedule;
    private String noisePreference;
    private String smokingPreference;

    public Roommate(int id, String fullName) {
        this.id       = id;
        this.fullName = fullName;
    }

    public int    getId()                   { return id; }
    public String getFullName()             { return fullName; }
    public String getEmail()                { return email; }
    public void   setEmail(String e)        { this.email = e; }
    public String getUniversity()           { return university; }
    public void   setUniversity(String u)   { this.university = u; }
    public String getGender()               { return gender; }
    public void   setGender(String g)       { this.gender = g; }
    public int    getCompatibilityScore()   { return compatibilityScore; }
    public void   setCompatibilityScore(int s) { this.compatibilityScore = s; }
    public String getCleanliness()          { return cleanliness; }
    public void   setCleanliness(String c)  { this.cleanliness = c; }
    public String getSleepSchedule()        { return sleepSchedule; }
    public void   setSleepSchedule(String s) { this.sleepSchedule = s; }
    public String getNoisePreference()      { return noisePreference; }
    public void   setNoisePreference(String n) { this.noisePreference = n; }
    public String getSmokingPreference()    { return smokingPreference; }
    public void   setSmokingPreference(String s) { this.smokingPreference = s; }

    public String getInitials() {
        if (fullName == null || fullName.isEmpty()) return "?";
        String[] parts = fullName.trim().split("\\s+");
        if (parts.length >= 2) return String.valueOf(parts[0].charAt(0)).toUpperCase()
                                     + String.valueOf(parts[1].charAt(0)).toUpperCase();
        return String.valueOf(parts[0].charAt(0)).toUpperCase();
    }
}
