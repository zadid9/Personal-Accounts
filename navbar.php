<div class="navbar">
    <!-- লোগো বা অ্যাপের নাম (ঐচ্ছিক) -->
    <!-- <div class="app-name">My Finance App</div> -->

    <div class="menu-icon" onclick="toggleMenu()">
        &#9776; <!-- Hamburger icon -->
    </div>

    <div class="nav-links-container" id="myNavLinks">
        <a href="dashboard.php">ড্যাশবোর্ড</a>
        <a href="add_transaction.php">নতুন লেনদেন</a>
        <a href="view_transactions.php">সকল লেনদেন</a>
        <!-- অ্যাকাউন্ট ড্রপডাউন (যদি প্রয়োজন হয়) -->
        <a href="view_accounts.php">অ্যাকাউন্ট ব্যালেন্স</a>
        <a href="add_account.php">অ্যাকাউন্ট যোগ করুন</a>

        <!-- ঋণ/ধারের লিংক -->
        <a href="view_loans.php">ধার/ঋণ অবস্থা</a>
        <a href="add_loan.php">ধার/ঋণ যোগ করুন</a>

        <!-- বাজেট লিংক -->
        <a href="view_budgets.php">বাজেট দেখুন</a>
        <a href="add_budget.php">বাজেট সেট করুন</a>

        <!-- ক্যাটাগরি লিংক -->
        <a href="view_categories.php">ক্যাটাগরি দেখুন</a>
        <a href="add_category.php">ক্যাটাগরি যোগ করুন</a>

        <a href="reports.php">রিপোর্ট</a>
        <a href="check_balances.php">হিসাব যাচাই</a>
        <a href="logout.php" class="logout-link">লগআউট</a>
    </div>
</div>

<script>
    function toggleMenu() {
        var x = document.getElementById("myNavLinks");
        x.classList.toggle("active");
    }

    // মোবাইল/ডেস্কটপের জন্য সঠিক ডিসপ্লে সেট করার জন্য প্রাথমিক চেক (ঐচ্ছিক, CSS দ্বারা নিয়ন্ত্রিত)
    window.addEventListener('resize', function() {
        var x = document.getElementById("myNavLinks");
        if (window.innerWidth > 768) {
            x.classList.remove("active"); // ডেস্কটপে মেনু বন্ধ রাখুন
        }
    });

    // ক্লিক করার সময় মেনু বন্ধ করার জন্য লজিক (ঐচ্ছিক)
    document.addEventListener('click', function(event) {
        var navbar = document.querySelector('.navbar');
        var navLinks = document.getElementById('myNavLinks');

        // যদি ক্লিকটি navbar এর বাইরে হয় এবং মেনু খোলা থাকে, তবে মেনু বন্ধ করুন
        if (!navbar.contains(event.target) && navLinks.classList.contains('active')) {
            navLinks.classList.remove('active');
        }
    });
</script>