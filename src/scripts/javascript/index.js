// Вывести предупреждение о действиях на случай утраты пароля
const forgotPasswordLink = document.querySelector('.forgot-password-link');

forgotPasswordLink.addEventListener('click', () => {
    alert("Для восстановления доступа обратитесь к администратору");
});
// Конец функции
