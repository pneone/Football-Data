const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));

gulp.task('scss', function () {
    return gulp
        .src('assets/scss/style.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest('assets/css'));
});

gulp.task('watch', function () {
    gulp.watch('assets/scss/**/*.scss', gulp.series('scss'));
});
