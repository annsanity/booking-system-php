<?php
// Database connection details
$host = '';
$db_database = "";
$db_username = "";
$db_password = "";
$db_charset = "";

// PDO connection setup
$dsn = "mysql:host=$host;dbname=$db_database;charset=$db_charset";
$opt = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
);

// Function to populate the topics dropdown
function populateTopics() {
    global $dsn, $db_username, $db_password;
    try {
        $pdo = new PDO($dsn, $db_username, $db_password);

        // Fetch distinct topic names from the database
        $stmt = $pdo->query("SELECT DISTINCT(topic_name) FROM sessions s JOIN timings t ON s.id = t.topic_id WHERE t.remaining_capacity > 0 ORDER BY topic_name");
        $selectedTopic = isset($_POST['topicName']) ? $_POST['topicName'] : '';

        // Add a default "Select a topic" option if no topic is selected
        if (!isset($_POST['topicName'])) {
            echo "<option value=\"default\">Select a topic</option>";
        }

        // Populate the topics dropdown
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $selected = ($row['topic_name'] == $selectedTopic) ? 'selected' : '';
            echo "<option value='" . $row['topic_name'] . "' $selected>" . $row['topic_name'] . "</option>";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}


// Function to populate the day and time dropdown
function populateDayTime($topic_name) {
    global $dsn, $db_username, $db_password, $opt;
    try {
        $pdo = new PDO($dsn, $db_username, $db_password, $opt);

        // Fetch available sessions for the selected topic
        $stmt = $pdo->prepare("SELECT t.session_id, t.day_of_week, t.time
                               FROM timings t
                               JOIN sessions s ON s.id = t.topic_id
                               WHERE s.topic_name = :topic_name and t.remaining_capacity > 0
                               ORDER BY t.day_of_week, t.time");
        $stmt->execute(array(':topic_name' => $topic_name));

        // Populate the day and time dropdown
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<option value='" . $row['session_id'] . "'>" . $row['day_of_week'] . ", " . $row['time'] . "</option>";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_btn'])) {
    try {
        $pdo = new PDO($dsn, $db_username, $db_password, $opt);

        // Retrieve form data
        $topicName = $_POST['topicName'];
        $sessionId = $_POST['day_time'];
        $name = $_POST['name'];
        $email = $_POST['email'];

        // Validate name and email
        if (!isValidName($name)) {
            echo "Booking Unsuccessful! Invalid name format. Please enter a valid name.";
            return;
        }

        if (!isValidEmail($email)) {
            echo "Booking Unsuccessful! Invalid email format. Please enter a valid email address.";
            return;
        }

        // Check the remaining capacity for the selected session
        $stmt = $pdo->prepare("SELECT remaining_capacity FROM timings WHERE session_id = :session_id");
        $stmt->execute(array(':session_id' => $sessionId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $remainingCapacity = $row['remaining_capacity'];

        // If there are available spots, book the session and display the booking details
        if ($remainingCapacity > 0) {
            $stmt = $pdo->prepare("UPDATE timings SET remaining_capacity = remaining_capacity - 1 WHERE session_id = :session_id");
            $stmt->execute(array(':session_id' => $sessionId));

            $stmt = $pdo->prepare("INSERT INTO bookings (session_id, name, email) VALUES (:session_id, :name, :email)");
            $stmt->execute(array(':session_id' => $sessionId, ':name' => $name, ':email' => $email));

            echo "<div class='success-message'>Booking successful!</div>";

            // Display the booked session details
            $stmt = $pdo->prepare("SELECT s.topic_name, t.day_of_week, t.time, b.name, b.email FROM sessions s JOIN timings t ON s.id = t.topic_id JOIN bookings b ON t.session_id = b.session_id WHERE b.email = :email");
            $stmt->execute(array(':email' => $email));
            if ($stmt->rowCount() > 0) {
                echo "<table border='1' style=\"margin: 0 auto;\">";
                echo "<tr><th>Topic</th><th>Day of Week</th><th>Time</th><th>Name</th><th>Email</th></tr>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr><td>{$row['topic_name']}</td><td>{$row['day_of_week']}</td><td>{$row['time']}</td><td>{$row['name']}</td><td>{$row['email']}</td></tr>";
                }
                echo "</table>\n";
                echo "<p>&nbsp;</p>"; // Add space
                echo "<p>Fill the form again for a new booking.</p>";
            } else {
                echo "<p>No bookings found.</p>";
            }
        } else {
            echo "No places available for the selected session.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Functions to validate name and email
function isValidName($name) {
    $pattern = '/^[a-zA-Z\']+([- ][a-zA-Z\']+)*$/';
    return preg_match($pattern, $name);
}

function isValidEmail($email) {
    $pattern = '/^[a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z]{2,}$/';
    return preg_match($pattern, $email);
}

?>

<!-- HTML code for the booking form -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Training Booking</title>
    <style>
        body {
            text-align: center;
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
        }
        form {
            margin: 20px auto;
            width: 300px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #007bff;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 10px;
        }
        select, input[type="text"], input[type="email"], input[type="submit"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .success-message {
            color: green;
            font-weight: bold;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h2>IT Training Booking</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="topic">Select Topic:</label>
        <select name="topicName" id="topic" onchange="this.form.submit()">
            <?php populateTopics(); ?>
        </select><br>

        <div>
            <label for="day_time">Select Day and Time:</label>
            <select name="day_time" id="day_time">
                <?php
                if (isset($_POST['topicName'])) {
                    populateDayTime($_POST['topicName']);
                }
                ?>
            </select><br>
        </div>

        <label for="name">Name:</label>
        <input type="text" name="name" id="name" required><br>

        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required><br>

        <input type="submit" name="submit_btn" value="Submit">
    </form>
</body>
</html>