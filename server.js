
const express = require("express");
const cors = require("cors");
const bodyParser = require("body-parser");

const app = express();
app.use(cors());
app.use(bodyParser.json());

// 🔐 Admin credentials (μπορείς να τα αλλάξεις)
const ADMIN = {
  username: "admin",
  password: "1234"
};

// 🔑 Login route
app.post("/login", (req, res) => {
  const { username, password } = req.body;

  if (username === ADMIN.username && password === ADMIN.password) {
    return res.json({ success: true, message: "Login successful" });
  } else {
    return res.status(401).json({ success: false, message: "Wrong credentials" });
  }
});

// 🟢 Test route
app.get("/", (req, res) => {
  res.send("Backend running 🚀");
});

const PORT = 5000;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
