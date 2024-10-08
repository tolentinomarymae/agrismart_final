<!-- single article section -->
<div class="mt-150 mb-150">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="single-article-section">
                    <div class="single-article-text">
                        <div class="single-article-image">
                            <img src="<?= base_url($trivia['image']) ?>" alt="Trivia Image" class="img-fluid">
                        </div>
                        <p class="blog-meta">
                            <span class="author"><i class="fas fa-user"></i> <?= $trivia['author'] ?></span>
                            <span class="date"><i class="fas fa-calendar"></i> <?= $trivia['date'] ?></span>
                        </p>
                        <h2></h2>
                        <h2><?= $trivia['triviatitle'] ?></h2>

                        <p><?= $trivia['trivia'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .single-article-image {
        text-align: center;
        margin-bottom: 20px;
    }

    .single-article-image img {
        max-width: 100%;
        height: auto;
    }
</style>