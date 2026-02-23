// Auto-hide messages after 3 seconds
function autoHideMessages() {
  const messages = document.querySelectorAll(
    ".success-message, .error-message, .debug-info"
  );

  messages.forEach((message) => {
    const closeBtn = document.createElement("button");
    closeBtn.className = "close-btn";
    closeBtn.innerHTML = "×";
    closeBtn.title = "بستن";
    closeBtn.onclick = function () {
      message.style.display = "none";
    };
    message.style.position = "relative";
    message.appendChild(closeBtn);

    setTimeout(() => {
      message.classList.add("message-hiding");
    }, 2500);

    setTimeout(() => {
      message.style.display = "none";
    }, 3000);
  });
}

function toggleSection(id) {
  const el = document.getElementById(id);
  el.classList.toggle("hidden");
}

function addRow() {
  let t = document.querySelector("#voucherItems tbody");
  let r = t.insertRow(-1);
  r.innerHTML = `<td><input name="details[]"></td>
                   <td>
                      <input name="general_code[]" list="codeList" oninput="showCodeBudgetInfo(this)">
                      <div class="code-suggestion"></div>
                   </td>
                   <td><input name="sub_code[]"></td>
                   <td><input type="number" step="0.01" name="debit[]" class="debit-input" oninput="checkBudgetLimit(this)"></td>
                   <td><input type="number" step="0.01" name="credit[]" class="credit-input"></td>`;

  r.querySelector(".debit-input").addEventListener("input", calculateTotals);
  r.querySelector(".credit-input").addEventListener("input", calculateTotals);
}

function showCodeBudgetInfo(input) {
  const suggestionDiv = input.nextElementSibling;
  const code = input.value.trim();
  const expenseType = document.querySelector(
    'select[name="expense_type"]'
  ).value;

  if (code.length > 0 && expenseType) {
    const codeData = window.codeData || {};
    const compositeKey = code + "_" + expenseType;

    if (codeData[compositeKey]) {
      const data = codeData[compositeKey];
      const remaining = data.remaining_budget;
      const spent = data.spent;
      const original = data.original_budget;
      const currentPercent = data.current_percent || 0;
      const bab = data.sub_code;

      suggestionDiv.innerHTML = `
                <div>
                    <span class="budget-ok">بودجه اصلی: ${original.toLocaleString()}</span><br>
                    <span>مصرف شده: ${spent.toLocaleString()}</span><br>
                    <span>بودجه باقیمانده: ${remaining.toLocaleString()}</span><br>
                    <span>درصد مصرف: ${currentPercent}%</span><br>
                    <span>باب: ${bab}</span>
                </div>
            `;

      if (remaining <= 0) {
        suggestionDiv.innerHTML +=
          '<span class="budget-exhausted">(بودجه تمام شده!)</span>';
      } else if (remaining < original * 0.1) {
        suggestionDiv.innerHTML +=
          '<span class="budget-warning">(بودجه در حال اتمام!)</span>';
      }
    } else {
      // Check if the code exists for another bab
      let codeExistsForOtherBab = false;
      for (let key in codeData) {
        if (codeData[key].general_code === code) {
          codeExistsForOtherBab = true;
          break;
        }
      }

      if (codeExistsForOtherBab) {
        suggestionDiv.innerHTML =
          '<span class="budget-error">این کوډ برای باب انتخاب شده معتبر نیست!</span>';
      } else {
        suggestionDiv.innerHTML =
          '<span class="budget-error">این کوډ در سیستم بودجه موجود نیست!</span>';
      }
    }
  } else if (code.length > 0) {
    suggestionDiv.innerHTML =
      '<span style="color: #999;">ابتدا نوعیت مصرف را انتخاب کنید</span>';
  } else {
    suggestionDiv.innerHTML = "";
  }
}

function checkBudgetLimit(input) {
  const row = input.closest("tr");
  const codeInput = row.querySelector('input[name="general_code[]"]');
  const expenseType = document.querySelector(
    'select[name="expense_type"]'
  ).value;
  const amount = parseFloat(input.value) || 0;

  if (codeInput.value && expenseType) {
    const codeData = window.codeData || {};
    const compositeKey = codeInput.value + "_" + expenseType;
    const data = codeData[compositeKey];

    if (data && data.sub_code === expenseType) {
      const remaining = data.remaining_budget;

      if (amount > remaining) {
        input.style.borderColor = "#dc3545";
        input.style.backgroundColor = "#ffe6e6";
        input.setCustomValidity(
          `مبلغ از بودجه باقیمانده (${remaining.toLocaleString()}) بیشتر است!`
        );
      } else {
        input.style.borderColor = "#28a745";
        input.style.backgroundColor = "#e6ffe6";
        input.setCustomValidity("");
      }
    } else {
      input.style.borderColor = "#dc3545";
      input.style.backgroundColor = "#ffe6e6";
      input.setCustomValidity("کوډ انتخاب شده برای این باب معتبر نیست!");
    }
  }
}

function calculateTotals() {
  let totalDebit = 0;
  let totalCredit = 0;

  document.querySelectorAll('input[name="debit[]"]').forEach((input) => {
    totalDebit += parseFloat(input.value) || 0;
  });

  document.querySelectorAll('input[name="credit[]"]').forEach((input) => {
    totalCredit += parseFloat(input.value) || 0;
  });

  const totalDebitInput = document.querySelector('input[name="total_debit"]');
  const totalCreditInput = document.querySelector('input[name="total_credit"]');
  const payableInput = document.querySelector('input[name="payable_amount"]');

  if (totalDebitInput) totalDebitInput.value = totalDebit.toFixed(2);
  if (totalCreditInput) totalCreditInput.value = totalCredit.toFixed(2);
  if (payableInput) payableInput.value = totalDebit.toFixed(2);
}

function validateForm() {
  let isValid = true;
  const expenseType = document.querySelector(
    'select[name="expense_type"]'
  ).value;
  const codeData = window.codeData || {};

  if (!expenseType) {
    alert("لطفاً نوعیت مصرف را انتخاب کنید!");
    return false;
  }

  document
    .querySelectorAll('input[name="general_code[]"]')
    .forEach((codeInput, index) => {
      const detail = document.querySelectorAll('input[name="details[]"]')[index]
        .value;
      const debitInput = document.querySelectorAll('input[name="debit[]"]')[
        index
      ];
      const debit = parseFloat(debitInput.value) || 0;

      if (detail.trim() && debit > 0) {
        const code = codeInput.value;

        if (!code) {
          alert(`ردیف ${index + 1}: کوډ عمومی را وارد کنید!`);
          isValid = false;
          return false;
        }

        const compositeKey = code + "_" + expenseType;

        if (codeData[compositeKey]) {
          if (codeData[compositeKey].sub_code !== expenseType) {
            alert(
              `ردیف ${
                index + 1
              }: کوډ "${code}" برای باب "${expenseType}" معتبر نیست!`
            );
            isValid = false;
            return false;
          }

          const remaining = codeData[compositeKey].remaining_budget;
          if (debit > remaining) {
            alert(
              `ردیف ${
                index + 1
              }: مبلغ مصرف (${debit.toLocaleString()}) از بودجه باقیمانده (${remaining.toLocaleString()}) بیشتر است!`
            );
            isValid = false;
            return false;
          }
        } else {
          // Check if code exists for other bab
          let codeExists = false;
          for (let key in codeData) {
            if (codeData[key].general_code === code) {
              codeExists = true;
              break;
            }
          }

          if (codeExists) {
            alert(
              `ردیف ${
                index + 1
              }: کوډ "${code}" برای باب "${expenseType}" معتبر نیست!`
            );
          } else {
            alert(
              `ردیف ${index + 1}: کوډ "${code}" در سیستم بودجه موجود نیست!`
            );
          }
          isValid = false;
          return false;
        }
      }
    });

  if (isValid) {
    return confirm("آیا از ثبت سند اطمینان دارید؟");
  }

  return false;
}

// Initialize event listeners
document.addEventListener("DOMContentLoaded", function () {
  autoHideMessages();

  document
    .querySelector('select[name="expense_type"]')
    .addEventListener("change", function () {
      document
        .querySelectorAll('input[name="general_code[]"]')
        .forEach((input) => {
          if (input.value) {
            showCodeBudgetInfo(input);
          }
        });
    });

  function attachEvents() {
    document
      .querySelectorAll('input[name="debit[]"], input[name="credit[]"]')
      .forEach((input) => {
        input.removeEventListener("input", calculateTotals);
        input.addEventListener("input", calculateTotals);
      });

    document
      .querySelectorAll('input[name="general_code[]"]')
      .forEach((input) => {
        input.addEventListener("input", function () {
          showCodeBudgetInfo(this);
          const row = this.closest("tr");
          const debitInput = row.querySelector('input[name="debit[]"]');
          if (debitInput && debitInput.value) {
            checkBudgetLimit(debitInput);
          }
        });
      });
  }

  attachEvents();
  calculateTotals();

  document.querySelectorAll('input[name="general_code[]"]').forEach((input) => {
    if (input.value) {
      showCodeBudgetInfo(input);
    }
  });
});
