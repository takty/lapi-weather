import gulp from 'gulp';
import plumber from 'gulp-plumber';
import changed, { compareContents } from 'gulp-changed';

gulp.task('php', () => gulp.src(['src/**/*.php'])
	.pipe(plumber())
	.pipe(changed('./dist', { hasChanged: compareContents }))
	.pipe(gulp.dest('./dist'))
);

gulp.task('watch', () => {
	gulp.watch('src/**/*.php', gulp.series('php'));
});

gulp.task('build', gulp.parallel('php'));

gulp.task('default', gulp.series('build', 'watch'));
