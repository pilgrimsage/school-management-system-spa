jQuery(document).ready(function () {
  var baseUrl = jQuery("#baseUrl").val();
  var storage = window.localStorage;
  function updateRoleUI() {
    let selected = $('input[name="type"]:checked').val();
    console.log(selected);
    if (selected === "student") {
      jQuery(".form-id-label").text("Student Id");
    } else {
      jQuery(".form-id-label").text("Teacher Id");
    }
  }

  // Listen for changes on the radio buttons
  jQuery('input[name="type"]').on("change", updateRoleUI);

  function showLoginError(message) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Login failed",
        text: message,
        confirmButtonColor: "#487FFF",
      });
    } else {
      alert(message);
    }
  }

  // Initialize on page load
  updateRoleUI();
  jQuery("#loginForm").on("submit", function (e) {
    e.preventDefault();
    var type = $("input[name='type']:checked").val();
    var $submitBtn = jQuery("#loginForm button[type='submit']").prop("disabled", true);

    $.ajax({
      url: baseUrl + "api/login",
      type: "POST",
      data: {
        email: $("#email").val(),
        password: $("#password").val(),
        type: type,
      },
      dataType: "json",
      complete: function () {
        $submitBtn.prop("disabled", false);
      },
      success: function (response) {
        if (!response.token) {
          showLoginError(response.message || "Invalid email/ID or password.");
          return;
        }

        const token = response.token;
        const loginType = type.trim();

        /* -----------------------------------------
				   1. Store token in localStorage (primary for SPA)
				----------------------------------------- */
        try {
          localStorage.setItem("authToken", token);
          localStorage.setItem("loginType", loginType);
        } catch (e) {
          console.warn("localStorage unavailable", e);
        }

        /* -----------------------------------------
				   2. Store token in cookie (browser reload fallback)
				----------------------------------------- */
        Cookies.set("authToken", token, {
          expires: 7, // 🔑 persistent
          path: "/",
          sameSite: "Lax",
        });

        Cookies.set("loginType", loginType, {
          expires: 7,
          path: "/",
          sameSite: "Lax",
        });

        /* -----------------------------------------
				   3. VERIFY persistence (important!)
				----------------------------------------- */
        const lsToken = localStorage.getItem("authToken");
        const ckToken = Cookies.get("authToken");

        if (!lsToken && !ckToken) {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "warning",
              title: "Storage blocked",
              text: "Your browser is blocking storage, so login may not persist after restart.",
              confirmButtonColor: "#487FFF",
            });
          } else {
            alert(
              "Your browser is blocking storage. " +
                "Login may not persist after restart.",
            );
          }
        }

        /* -----------------------------------------
				   4. Redirect
				----------------------------------------- */
        if (loginType === "student") {
          window.location.href = baseUrl + "post-login-student/dashboard";
        } else {
          window.location.href =
            baseUrl + "post-login-employee/admin/view-modules";
        }
      },
      error: function (xhr) {
        let message = "Something went wrong. Please try again.";
        try {
          const parsed = JSON.parse(xhr.responseText);
          message = parsed.message || message;
        } catch (e) {
          // Non-JSON error body (e.g. a raw 500 page) — keep the default message.
        }
        showLoginError(message);
      },
    });
  });
});
