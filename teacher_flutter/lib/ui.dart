import 'package:material_ui/material_ui.dart';

final appTheme = ValueNotifier(ThemeMode.light); // Memory only.
const forest = Color(0xff185b4c);
const gold = Color(0xffa48646);
ThemeData halakaTheme(Brightness brightness) {
  final dark = brightness == Brightness.dark;
  final border = dark ? const Color(0xff365043) : const Color(0xffdce4dc);
  final surface = dark ? const Color(0xff1d3029) : Colors.white;
  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    fontFamily: 'Tajawal',
    colorScheme: ColorScheme.fromSeed(seedColor: forest, brightness: brightness)
        .copyWith(
          primary: dark ? const Color(0xff78b99b) : forest,
          secondary: gold,
          surface: surface,
          outlineVariant: border,
        ),
    scaffoldBackgroundColor: dark
        ? const Color(0xff152520)
        : const Color(0xfff5f6f1),
    appBarTheme: AppBarTheme(
      backgroundColor: dark ? const Color(0xff152520) : const Color(0xfff5f6f1),
      surfaceTintColor: Colors.transparent,
      elevation: 0,
    ),
    cardTheme: CardThemeData(
      color: surface,
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: border),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: surface,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: border),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(48, 48),
        backgroundColor: forest,
        foregroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(48, 48),
        side: BorderSide(color: border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: surface,
      indicatorColor: dark ? const Color(0xff263d32) : const Color(0xffedf3ed),
    ),
  );
}

class Panel extends StatelessWidget {
  final Widget child;
  const Panel({super.key, required this.child});
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(padding: const EdgeInsets.all(16), child: child),
  );
}

class StatusPill extends StatelessWidget {
  final String label;
  final bool pending;
  const StatusPill(this.label, {super.key, this.pending = false});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(
      color: (pending ? gold : forest).withValues(alpha: .12),
      borderRadius: BorderRadius.circular(7),
    ),
    child: Text(
      label,
      style: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        color: Theme.of(context).brightness == Brightness.dark
            ? (pending ? const Color(0xffeed69a) : const Color(0xffb2d9b6))
            : (pending ? const Color(0xff795c1e) : forest),
      ),
    ),
  );
}

String dateLabel(BuildContext context, dynamic value) {
  final date = DateTime.tryParse('$value')?.toLocal();
  return date == null
      ? ''
      : MaterialLocalizations.of(context).formatMediumDate(date);
}

String timeLabel(BuildContext context, dynamic value) {
  final date = DateTime.tryParse('$value')?.toLocal();
  return date == null
      ? ''
      : MaterialLocalizations.of(context)
            .formatTimeOfDay(TimeOfDay.fromDateTime(date));
}
