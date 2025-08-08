(function ($) {
  var state = {
    products: [],
    selectedProducts: new Map(), // Store product data by ID
    preview: null,
    jobId: null,
    settings: null,
    searchTimeout: null,
  };

  // SPA navigation inside our page
  function nav(to) {
    $(".brw-shell .brw-main > section").hide();
    $(".brw-shell .brw-nav a").removeClass("active");
    $(".brw-shell .brw-nav a[data-target='" + to + "']").addClass("active");
    $("#brw-section-" + to).show();
  }

  function step(to) {
    $("#brw-step-products, #brw-step-config, #brw-step-progress").hide();
    $(".brw-steps li").removeClass("active");
    if (to === "config") {
      $("#brw-step-config").show();
      $(".brw-steps li").eq(1).addClass("active");
    } else if (to === "progress") {
      $("#brw-step-progress").show();
      $(".brw-steps li").eq(2).addClass("active");
    } else {
      $("#brw-step-products").show();
      $(".brw-steps li").eq(0).addClass("active");
    }
  }

  // Update selected products list display
  function updateSelectedProductsDisplay() {
    var container = $("#brw-selected-list");
    container.empty();

    state.selectedProducts.forEach(function (product, id) {
      var tag = $(
        '<div class="brw-product-tag">' +
          (product.thumb
            ? '<img class="tag-thumb" src="' + product.thumb + '" alt="" />'
            : "") +
          '<span class="tag-name">' +
          product.text +
          "</span>" +
          '<button class="remove" data-id="' +
          id +
          '">×</button>' +
          "</div>"
      );
      container.append(tag);
    });

    // Update state.products array
    state.products = Array.from(state.selectedProducts.keys()).map((id) =>
      parseInt(id)
    );
    $("#brw-next-to-config").prop("disabled", state.products.length === 0);
  }

  // Product autocomplete search
  function performProductSearch(query) {
    if (!query || query.length < 2) {
      $("#brw-autocomplete").removeClass("show");
      return;
    }

    var cat = $("#brw-cat").val();
    var exclude = $("#brw-exclude-reviewed").is(":checked") ? 1 : 0;

    var params = {
      action: "brw_search_products",
      nonce: BRW.nonce,
      q: query,
      exclude_reviewed: exclude,
    };
    if (cat) params.category = cat; // pass a concrete value only when present

    $.get(BRW.ajax.url, params)
      .done(function (res) {
        if (!res.success) {
          $("#brw-autocomplete").removeClass("show");
          return;
        }

        var container = $("#brw-autocomplete");
        container.empty();

        if (res.data.items.length === 0) {
          container.html(
            '<div class="brw-autocomplete-item">No products found</div>'
          );
        } else {
          res.data.items.forEach(function (product) {
            var item = $(
              '<div class="brw-autocomplete-item" data-id="' +
                product.id +
                '" data-thumb="' +
                (product.thumb || "") +
                '">' +
                '<div class="product-row">' +
                '<img class="product-thumb" src="' +
                (product.thumb || "") +
                '" alt="" />' +
                '<div class="product-meta">' +
                '<div class="product-name">' +
                product.text +
                "</div>" +
                '<div class="product-details">SKU: ' +
                (product.sku || "N/A") +
                " | Reviews: " +
                (product.reviews || 0) +
                " | Price: $" +
                (product.price || "0") +
                "</div>" +
                "</div>" +
                "</div>"
            );
            container.append(item);
          });
        }

        container.addClass("show");
      })
      .fail(function () {
        $("#brw-autocomplete").removeClass("show");
      });
  }

  // init sidebar links
  $(document).on("click", ".brw-shell .brw-nav a", function (e) {
    e.preventDefault();
    nav($(this).data("target"));
  });

  // Product search with autocomplete
  $("#brw-search").on("input", function () {
    var query = $(this).val();

    clearTimeout(state.searchTimeout);
    state.searchTimeout = setTimeout(function () {
      performProductSearch(query);
    }, 300);
  });

  // Handle autocomplete item selection
  $(document).on("click", ".brw-autocomplete-item", function () {
    var productId = $(this).data("id");
    if (!productId) return;

    var productName = $(this).find(".product-name").text();
    var productDetails = $(this).find(".product-details").text();
    var productThumb =
      $(this).data("thumb") || $(this).find(".product-thumb").attr("src") || "";

    // Add to selected products if not already selected
    if (!state.selectedProducts.has(productId.toString())) {
      state.selectedProducts.set(productId.toString(), {
        id: productId,
        text: productName,
        details: productDetails,
        thumb: productThumb,
      });
      updateSelectedProductsDisplay();
    }

    // Clear search and hide autocomplete
    $("#brw-search").val("");
    $("#brw-autocomplete").removeClass("show");
  });

  // Remove selected product
  $(document).on("click", ".brw-product-tag .remove", function () {
    var productId = $(this).data("id").toString();
    state.selectedProducts.delete(productId);
    updateSelectedProductsDisplay();
  });

  // Hide autocomplete when clicking outside
  $(document).on("click", function (e) {
    if (!$(e.target).closest(".brw-search-wrapper").length) {
      $("#brw-autocomplete").removeClass("show");
    }
  });

  // Legacy support - remove old search button handler if it exists
  $("#brw-search-btn").off("click");

  $("#brw-next-to-config").on("click", function () {
    step("config");
  });
  $("#brw-back-to-products").on("click", function () {
    step("products");
  });
  $("#brw-back-to-config").on("click", function () {
    step("config");
  });

  $("#brw-preview").on("click", function () {
    // Build an initial Awaiting list from selected products
    var perProduct = parseInt($("#brw-count").val(), 10) || 5;
    var $awaiting = $("#brw-awaiting-log");
    if ($awaiting.length) {
      $awaiting.empty();
      state.selectedProducts.forEach(function (p) {
        var text = p.text + " — 0/" + perProduct;
        $awaiting.append(
          '<div class="brw-awaiting-item">' +
            $("<div>").text(text).html() +
            "</div>"
        );
      });
    }
    $("#brw-added-log").empty();
    $("#brw-progress-bar").css("width", "0%");
    $("#brw-progress-label").text(
      "0% (0/" + state.products.length * perProduct + ")"
    );
    $("#brw-generate").prop("disabled", false);
    step("progress");
    nav("generate");
  });

  $("#brw-generate").on("click", function () {
    var dist = {};
    $(".brw-rate").each(function () {
      dist[$(this).data("rate")] = parseInt(this.value, 10) || 0;
    });
    var manualAuthors = $("#brw-authors").val() || "";
    var manualReviews = $("#brw-reviews").val() || "";
    var payload = {
      products: state.products,
      reviews_per_product: parseInt($("#brw-count").val(), 10) || 5,
      rating_distribution: dist,
      language: $("#brw-language").val() || "en_US",
      author_settings: {
        verified_purchases: parseInt($("#brw-verified").val(), 10) || 0,
        manual_authors: manualAuthors,
      },
      manual_reviews: manualReviews,
      date_range: {
        start: $("#brw-date-start").val() || null,
        end: $("#brw-date-end").val() || null,
      },
    };
    $("#brw-generate").prop("disabled", true);
    $("#brw-progress").show();
    $.post(BRW.ajax.url, {
      action: "brw_start_generation",
      nonce: BRW.nonce,
      settings: JSON.stringify(payload),
    }).done(function (res) {
      if (!res.success) {
        alert("Start failed");
        return;
      }
      state.jobId = res.data.job_id;
      poll();
    });
  });

  function poll() {
    $.get(BRW.ajax.url, {
      action: "brw_generation_progress",
      nonce: BRW.nonce,
      job_id: state.jobId,
    }).done(function (res) {
      if (!res.success) {
        return;
      }
      var status = res.data.status || "pending";
      var percent = res.data.percent || 0;
      var processed =
        res.data.results && res.data.results.processed
          ? res.data.results.processed
          : 0;
      var total =
        res.data.results && res.data.results.total ? res.data.results.total : 0;
      var log = res.data.log || [];
      var queue = res.data.queue || [];

      // Animate progress bar
      $("#brw-progress-bar").css("width", percent + "%");
      $("#brw-progress-label").text(
        percent + "% (" + processed + "/" + total + ")"
      );

      // Live log: Added
      var $added = $("#brw-added-log");
      if ($added.length) {
        $added.empty();
        log.slice(-100).forEach(function (entry) {
          if (entry.status === "added") {
            $added.append(
              '<div class="brw-log-entry added"><strong>' +
                $("<div>").text(entry.product).html() +
                "</strong></div>"
            );
          } else if (entry.status === "error") {
            $added.append(
              '<div class="brw-log-entry error"><strong>' +
                $("<div>").text(entry.product).html() +
                '</strong> <span class="brw-log-error">Error: ' +
                $("<div>")
                  .text(entry.message || "")
                  .html() +
                "</span></div>"
            );
          }
        });
        $added.scrollTop($added[0].scrollHeight);
      }

      // Awaiting panel from queue
      var $awaiting = $("#brw-awaiting-log");
      if ($awaiting.length) {
        $awaiting.empty();
        queue.forEach(function (q) {
          var text =
            q.product + " — " + q.completed + "/" + (q.completed + q.remaining);
          $awaiting.append(
            '<div class="brw-awaiting-item">' +
              $("<div>").text(text).html() +
              "</div>"
          );
        });
      }

      if (status === "completed" || status === "failed") {
        $("#brw-generate").prop("disabled", false);
        loadJobs();
        return;
      }
      setTimeout(poll, 2000);
    });
    // No dynamic insertion of extra logs; template already contains them
  }

  // Settings
  function loadSettings() {
    $.get(BRW.ajax.url, { action: "brw_get_settings", nonce: BRW.nonce }).done(
      function (res) {
        if (res.success) {
          state.settings = res.data.settings || {};
          $("#brw-provider").val(state.settings.provider || "openai");
          $("#brw-model").val(state.settings.model || "");
          $("#brw-api-key").val(state.settings.api_key || "");
          $("#brw-base-url").val(state.settings.base_url || "");
          $("#brw-temperature").val(state.settings.temperature || 0.7);
          $("#brw-max-tokens").val(state.settings.max_tokens || 150);
          $("#brw-rate-limit").val(state.settings.rate_limit || 60);
        }
      }
    );
  }

  $("#brw-save-settings").on("click", function () {
    var settings = {
      provider: $("#brw-provider").val() || "openai",
      model: $("#brw-model").val() || "",
      api_key: $("#brw-api-key").val() || "",
      base_url: $("#brw-base-url").val() || "",
      temperature: parseFloat($("#brw-temperature").val() || 0.7),
      max_tokens: parseInt($("#brw-max-tokens").val() || 150),
      rate_limit: parseInt($("#brw-rate-limit").val() || 60),
    };
    $("#brw-save-settings").prop("disabled", true).text("Saving…");
    $.post(BRW.ajax.url, {
      action: "brw_save_settings",
      nonce: BRW.nonce,
      settings: JSON.stringify(settings),
    }).done(function (res) {
      $("#brw-save-settings").prop("disabled", false).text("Save");
    });
  });

  // Jobs list
  function loadJobs() {
    $.get(BRW.ajax.url, { action: "brw_list_jobs", nonce: BRW.nonce }).done(
      function (res) {
        if (!res.success) return;
        var jobs = res.data.jobs || [];
        var html =
          '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Created</th><th>Completed</th></tr></thead><tbody>';
        jobs.forEach(function (j) {
          html +=
            "<tr><td>" +
            j.id +
            "</td><td>" +
            (j.job_name || "-") +
            "</td><td>" +
            j.status +
            "</td><td>" +
            (j.created_at || "-") +
            "</td><td>" +
            (j.completed_at || "-") +
            "</td></tr>";
        });
        html += "</tbody></table>";
        $("#brw-jobs-list").html(html);
      }
    );
  }

  // initial
  nav("generate");
  step();
  loadSettings();
  loadJobs();
})(jQuery);
