document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("idCardForm");

    form.addEventListener("submit", function (event) {
        let isValid = true;

        function showError(inputId, message) {
            const errorElement = document.getElementById(inputId + "Error");
            errorElement.textContent = message;
            errorElement.style.color = "red";
            isValid = false;
        }

        function clearError(inputId) {
            document.getElementById(inputId + "Error").textContent = "";
        }

        // Name Validation
        const name = document.getElementById("name").value.trim();
        if (name === "") {
            showError("name", "Name is required.");
        } else {
            clearError("name");
        }

        // Designation Validation
        const designation = document.getElementById("designation").value.trim();
        if (designation === "") {
            showError("designation", "Designation is required.");
        } else {
            clearError("designation");
        }

        // Employee Code Validation
        const empCode = document.getElementById("emp_code").value.trim();
        if (!/^[A-Za-z0-9]+$/.test(empCode)) {
            showError("emp_code", "Only letters and numbers allowed.");
        } else {
            clearError("emp_code");
        }

        // Blood Group Validation
        const bloodGroup = document.getElementById("blood_group").value.trim();
        if (bloodGroup && !/^(A|B|AB|O)[+-]$/.test(bloodGroup)) {
            showError("blood_group", "Enter a valid blood group (A+, O-, etc.).");
        } else {
            clearError("blood_group");
        }

        // Address Validation
        const address = document.getElementById("address").value.trim();
        if (address === "") {
            showError("address", "Office Address is required.");
        } else {
            clearError("address");
        }

        // Employee Photo Validation
        const photo = document.getElementById("employee_photo").files[0];
        if (!photo) {
            showError("employee_photo", "Employee photo is required.");
        } else {
            clearError("employee_photo");
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
});
