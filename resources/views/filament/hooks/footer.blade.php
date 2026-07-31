<style>
    .silogy-panel-footer {
        padding: .75rem 1rem;
        background-color: #ffffff;
        color: #212529;
        text-align: center;
        font-size: .75rem;
        border-top: 3px solid #009900;
    }

    .silogy-panel-footer a {
        color: #007000;
        font-weight: 600;
    }

    .silogy-panel-footer a:hover {
        color: #009900;
    }

    .dark .silogy-panel-footer {
        background-color: #18181b;
        color: #f8f9fa;
    }

    .dark .silogy-panel-footer a {
        color: #4dcc4d;
    }

    .dark .silogy-panel-footer a:hover {
        color: #80d980;
    }

    @media (min-width: 768px) {
        .silogy-panel-footer { padding-inline: 1.5rem; }
    }

    @media (min-width: 1024px) {
        .silogy-panel-footer { padding-inline: 2rem; }
    }
</style>
<footer class="silogy-panel-footer">
    &copy; {{ date('Y') }} SILOGY. All rights reserved.
    &middot; LPMPP Universitas Siliwangi &middot;
    <a href="https://lpmpp.unsil.ac.id" target="_blank" rel="noopener">lpmpp.unsil.ac.id</a>
</footer>
