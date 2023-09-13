# Generated by Django 4.2.4 on 2023-09-13 15:43

from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('material', '0012_alter_compilation_status'),
    ]

    operations = [
        migrations.AlterField(
            model_name='compilation',
            name='status',
            field=models.CharField(choices=[('building', 'Building'), ('built', 'Built'), ('error', 'Error during building'), ('not_built', 'Not built')], default='building', max_length=10),
        ),
    ]
