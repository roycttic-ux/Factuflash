package com.example.myapplication.network;

import com.google.gson.annotations.SerializedName;

public class AuthResponse {
    @SerializedName("token")
    private String token;
    
    @SerializedName("user")
    private User user;

    public String getToken() { return token; }
    public User getUser() { return user; }

    public static class User {
        @SerializedName("id")
        private int id;
        @SerializedName("name")
        private String name;
        @SerializedName("email")
        private String email;

        public int getId() { return id; }
        public String getName() { return name; }
        public String getEmail() { return email; }
    }
}
