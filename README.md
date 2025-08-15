# Recipe Box

A modern, feature-rich recipe management system built with PHP, featuring image uploads, user reviews, tagging, and a beautiful responsive interface.

## Features

### 🍳 Recipe Management
- **Create & Edit Recipes**: Easy-to-use forms for adding new recipes and editing existing ones
- **Rich Content**: Support for titles, descriptions, ingredients, and step-by-step instructions
- **Image Support**: Base images for recipes with review photo override functionality
- **Tagging System**: Categorize recipes with custom tags for easy organization

### 📱 User Experience
- **Responsive Design**: Modern Bootstrap-based interface that works on all devices
- **Search & Filter**: Find recipes by title, ingredients, description, or tags
- **Smart Sorting**: Recipes automatically sorted by rating (high to low) then alphabetically
- **Popular Tags**: Clickable popular tags for quick recipe discovery

### 📸 Photo Management
- **Review Photos**: Users can upload photos with their reviews
- **Image Fallback**: Smart image selection (review photos > base images > no image)
- **Secure Uploads**: File type validation and size limits for security

### ⭐ Rating & Reviews
- **User Reviews**: Rate recipes from 1-5 stars with comments and photos
- **Photo Uploads**: Take photos with camera or upload image files
- **Rating System**: Automatic calculation of average ratings and vote counts

### 🔒 Security Features
- **CSRF Protection**: Built-in CSRF token validation
- **Admin PIN**: Secure admin access for recipe management
- **Input Sanitization**: All user inputs are properly sanitized

## Technical Details

### Requirements
- PHP 7.4+ (with file upload support)
- Web server (Apache/Nginx)
- File system write permissions for uploads

### File Structure
```
recipes/
├── data/recipes/          # Recipe JSON files
├── lib/                   # Core functions and CSS
│   ├── functions.php     # Main application logic
│   └── theme.css        # Custom styling
├── uploads/recipes/      # User uploaded photos
├── edit.php             # Recipe editing interface
├── index.php            # Homepage with search and recipe cards
├── new.php              # New recipe creation
├── recipe.php           # Individual recipe display
└── save.php             # Recipe saving logic
```

### Key Functions
- `load_recipe()`: Load recipe data from JSON files
- `save_recipe()`: Save recipe data with proper formatting
- `best_review_photo()`: Select best photo for recipe display
- `search_recipes()`: Full-text search across all recipe fields
- `list_recipes()`: Get all recipes with smart sorting

## Setup Instructions

1. **Clone the repository**:
   ```bash
   git clone <your-repo-url>
   cd recipes
   ```

2. **Set permissions**:
   ```bash
   chmod 755 uploads/recipes/
   chmod 644 data/recipes/
   ```

3. **Configure admin PIN**:
   - Create `data/admin_pin.txt` with your desired PIN
   - Or set `RECIPE_ADMIN_PIN` environment variable

4. **Web server configuration**:
   - Ensure PHP has file upload permissions
   - Configure proper file size limits in `php.ini`

## Usage

### Adding Recipes
1. Click "New Recipe" button
2. Fill in recipe details (title, description, ingredients, steps)
3. Add optional image URL and tags
4. Enter admin PIN to save

### Managing Recipes
1. Click "Edit" on any recipe
2. Modify content as needed
3. Enter admin PIN to save changes

### Adding Reviews
1. Navigate to any recipe page
2. Fill out the review form
3. Add optional photo (upload or URL)
4. Submit review

### Searching Recipes
- Use the search box for text-based searches
- Click popular tags for instant filtering
- Search works across titles, descriptions, ingredients, and tags

## Customization

### Styling
- Modify `lib/theme.css` for custom colors and styling
- CSS variables are defined for easy theme changes

### Tags
- Tags are automatically extracted and ranked by popularity
- Custom tag categories can be added through the recipe forms

### Image Handling
- Review photos automatically override base images
- Supports JPG, PNG, WEBP, and GIF formats
- Configurable file size limits

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Support

For issues or questions:
1. Check existing issues in the repository
2. Create a new issue with detailed description
3. Include PHP version and server environment details

---

**Recipe Box** - Making recipe management simple and beautiful! 🎉
