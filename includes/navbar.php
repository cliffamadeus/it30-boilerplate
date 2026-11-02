<?php
// includes/navbar.php
// Determines the active section from the current script or query string.
// Falls back to 'dashboard' as the default.

$currentSection = $_GET['section'] ?? 'dashboard';

// Helper to mark the active link
function navActive($section, $currentSection) {
    return $section === $currentSection ? 'active' : '';
}
?>
<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
    /* Scoped navbar styles */
    .app-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    }

    .app-header h1 {
        font-weight: 500;
        font-size: 1.8rem;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #0f172a;
        margin: 0;
    }

    .app-header h1 .material-icons {
        font-size: 2rem;
        color: #1e293b;
    }

    .nav-links {
        display: flex;
        gap: 0.5rem;
        background: #ffffff;
        padding: 0.35rem;
        border-radius: 3rem;
        border: 1px solid #e9eef2;
    }

    .nav-links a {
        text-decoration: none;
        color: #334155;
        font-size: 0.9rem;
        padding: 0.5rem 1.2rem;
        border-radius: 2rem;
        transition: 0.15s;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .nav-links a .material-icons {
        font-size: 1.1rem;
    }

    .nav-links a:hover {
        background: #f1f5f9;
    }

    .nav-links a.active {
        background: #1e293b;
        color: white;
    }

    .nav-links a.active .material-icons {
        color: white;
    }

    @media (max-width: 760px) {
        .app-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .nav-links {
            width: 100%;
            justify-content: space-between;
        }
        .nav-links a {
            padding: 0.5rem 0.8rem;
            font-size: 0.8rem;
        }
    }
</style>

<header class="app-header">
    <h1>
        <span class="material-icons">local_library</span>
        library dashboard
    </h1>
    <nav class="nav-links">
        <a href="index.php?section=dashboard" class="<?= navActive('dashboard', $currentSection) ?>">
            <span class="material-icons">dashboard</span>
            Dashboard
        </a>
        <a href="index.php?section=students" class="<?= navActive('students', $currentSection) ?>">
            <span class="material-icons">people</span>
            Students
        </a>
        <a href="index.php?section=books" class="<?= navActive('books', $currentSection) ?>">
            <span class="material-icons">menu_book</span>
            Books
        </a>
        <a href="index.php?section=borrow" class="<?= navActive('borrow', $currentSection) ?>">
            <span class="material-icons">swap_horiz</span>
            Borrow
        </a>
    </nav>
</header>